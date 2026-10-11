<?php

declare(strict_types=1);

use Akira\Efatura\Commands\ValidateCommand;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Tests\Support\CertificateFixtures as C;
use Akira\Efatura\Tests\Support\SignatureEdits as E;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

function validationFile(string $xml): string
{
    $path = test()->directory . '/' . Str::uuid()->toString() . '.xml';
    file_put_contents($path, $xml);

    return $path;
}

function validationJson(string $path, array $options = []): array
{
    $exitCode = Artisan::call('efatura:validate', ['path' => $path, '--json' => true, ...$options]);

    return [$exitCode, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
}

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir() . '/efatura-validate-' . Str::uuid()->toString();
    new Filesystem()->ensureDirectoryExists($this->directory);
    Http::fake();
});

afterEach(function (): void {
    chmod($this->directory, 0o755);
    new Filesystem()->deleteDirectory($this->directory);
    Http::assertNothingSent();
});

it('declares its signature and description with Laravel command attributes', function (): void {
    $command = new ReflectionClass(ValidateCommand::class);

    expect($command->getAttributes(Signature::class)[0]->newInstance()->signature)->toStartWith('efatura:validate {path')
        ->and($command->getAttributes(Description::class)[0]->newInstance()->description)
        ->toBe('Validate a signed e-Fatura XML file against the XSD and verify its signature')
        ->and(resolve(ValidateCommand::class)->getDefinition()->getOptions())->toHaveKeys(['profile', 'json']);
});

it('accepts a document signed by the package and says the chain is not verified', function (string $kind, SignatureProfile $profile): void {
    $path    = validationFile(S::sign(S::unsigned($kind), $profile)->xml);
    $name    = $profile === SignatureProfile::Enveloped ? 'enveloped' : 'detached';
    $options = [[], ['--profile' => $name]];

    foreach ($options as $option) {
        artisan('efatura:validate', ['path' => $path, ...$option])
            ->expectsOutputToContain('Schema (' . $name . '): valid.')
            ->expectsOutputToContain('Signature: valid.')
            ->expectsOutputToContain('Certificate in KeyInfo: CN=e-Fatura Test Signer,O=e-Fatura Test,C=CV, serial number 1715004, SHA-256 ')
            ->expectsOutputToContain('The certificate chain was not verified against ICP-CV or any other authority.')
            ->expectsOutputToContain('The document is valid.')
            ->assertExitCode(0);
    }
})->with(S::documentsAndProfiles());

it('rejects a tampered signed document', function (Closure $tamper, string $message): void {
    $path = validationFile($tamper(S::sign(S::unsigned())->xml));

    artisan('efatura:validate', ['path' => $path])
        ->expectsOutputToContain('Signature: invalid.')
        ->expectsOutputToContain($message)
        ->expectsOutputToContain('The document is not valid.')
        ->assertExitCode(1);
})->with([
    'a changed amount' => [
        fn (string $xml): string => str_replace('<PayableAmount>115</PayableAmount>', '<PayableAmount>116</PayableAmount>', $xml),
        'A signed digest does not match: the content or its signed properties changed after signing. (signature.digest_mismatch)',
    ],
    'a changed signature value' => [
        fn (string $xml): string => S::flipBase64($xml, '//ds:SignatureValue'),
        'The signature value does not verify against the certificate in KeyInfo. (signature.value_invalid)',
    ],
    'a swapped certificate' => [
        fn (string $xml): string => E::edit($xml, E::replaceCertificate(base64_encode(C::issue(key: C::key('other'))->der()))),
        'The signing certificate digest does not match the certificate in KeyInfo. (signature.certificate_mismatch)',
    ],
]);

it('rejects an unsigned document, which the enveloped schema alone accepts', function (): void {
    artisan('efatura:validate', ['path' => validationFile(S::unsigned())])
        ->expectsOutputToContain('Schema (enveloped): valid.')
        ->expectsOutputToContain('The document has no signature. (signature.missing)')
        ->expectsOutputToContain('The document is not valid.')
        ->assertExitCode(1);
});

it('validates against the profile it is told to use', function (): void {
    artisan('efatura:validate', ['path' => validationFile(S::sign(S::unsigned(), SignatureProfile::InternallyDetached)->xml), '--profile' => 'enveloped'])
        ->expectsOutputToContain('Schema (enveloped): invalid (xml.schema_invalid).')
        ->expectsOutputToContain('No matching global declaration available for the validation root.')
        ->expectsOutputToContain('Signature: not checked, because the schema validation failed.')
        ->doesntExpectOutputToContain('Signature: valid.')
        ->doesntExpectOutputToContain('Certificate in KeyInfo')
        ->expectsOutputToContain('The document is not valid.')
        ->assertExitCode(1);
});

it('refuses xml the hardened parser does not accept', function (string $xml, string $code): void {
    artisan('efatura:validate', ['path' => validationFile($xml)])
        ->expectsOutputToContain('XML: refused before schema validation (' . $code . ').')
        ->doesntExpectOutputToContain('Signature:')
        ->expectsOutputToContain('The document is not valid.')
        ->assertExitCode(1);

    [$exitCode, $document] = validationJson(validationFile($xml));

    expect($exitCode)->toBe(1)
        ->and($document)->toMatchArray(['valid' => false, 'profile' => null, 'signature' => null, 'trust_chain_verified' => false])
        ->and($document['schema']['code'])->toBe($code);
})->with([
    'malformed xml'  => ['<Dfe><unclosed></Dfe>', 'xml.malformed'],
    'an xxe attempt' => ['<?xml version="1.0"?><!DOCTYPE Dfe [<!ENTITY x SYSTEM "file:///etc/passwd">]><Dfe>&x;</Dfe>', 'xml.doctype_forbidden'],
]);

it('prints a stable json document', function (): void {
    $signed = S::sign(S::unsigned());

    [$exitCode, $document] = validationJson(validationFile($signed->xml));

    expect($exitCode)->toBe(0)
        ->and($document)->toBe([
            'valid'     => true,
            'profile'   => 'enveloped',
            'schema'    => ['valid' => true, 'code' => null, 'violations' => [], 'violations_total' => 0],
            'signature' => [
                'valid'       => true,
                'failures'    => [],
                'certificate' => [
                    'subject'       => 'CN=e-Fatura Test Signer,O=e-Fatura Test,C=CV',
                    'serial_number' => '1715004',
                    'digest'        => $signed->certificateDigest,
                ],
            ],
            'trust_chain_verified' => false,
        ]);
});

it('prints the violations of a document the schema refuses as json, without checking its signature', function (): void {
    [$exitCode, $document] = validationJson(validationFile(S::sign(S::unsigned(), SignatureProfile::InternallyDetached)->xml), ['--profile' => 'enveloped']);

    expect($exitCode)->toBe(1)
        ->and($document['valid'])->toBeFalse()
        ->and($document['schema']['valid'])->toBeFalse()
        ->and(array_keys($document['schema']['violations'][0]))->toBe(['line', 'column', 'message'])
        ->and($document['signature'])->toBeNull();
});

it('prints the failures of a signature the schema accepts as json', function (): void {
    $xml = E::edit(S::sign(S::unsigned())->xml, E::replaceCertificate(base64_encode('not a certificate')));

    [$exitCode, $document] = validationJson(validationFile($xml));

    expect($exitCode)->toBe(1)
        ->and($document['schema'])->toBe(['valid' => true, 'code' => null, 'violations' => [], 'violations_total' => 0])
        ->and($document['signature'])->toBe(['valid' => false, 'failures' => ['signature.certificate_invalid'], 'certificate' => null]);
});

it('prints the first hundred violations and how many it left out', function (): void {
    $xml  = S::unsigned();
    $line = Str::between($xml, '<Lines>', '</Lines>');
    $path = validationFile(str_replace($line, str_repeat(str_replace('LineTypeCode="N"', 'LineTypeCode="Q"', $line), 150), $xml));

    artisan('efatura:validate', ['path' => $path])
        ->expectsOutputToContain('Schema (enveloped): invalid (xml.schema_invalid).')
        ->expectsOutputToContain('50 more violations are not shown.')
        ->assertExitCode(1);

    [$exitCode, $document] = validationJson($path);

    expect($exitCode)->toBe(1)
        ->and($document['schema']['violations'])->toHaveCount(100)
        ->and($document['schema']['violations_total'])->toBe(150);
});

it('refuses paths and options it cannot validate', function (Closure $path, array $options, string $reason, string $message): void {
    $target = $path();

    artisan('efatura:validate', ['path' => $target, ...$options])
        ->expectsOutputToContain(str_replace(':path', $target, $message))
        ->assertExitCode(1);

    [$exitCode, $document] = validationJson($target, $options);

    expect($exitCode)->toBe(1)
        ->and($document)->toBe(['valid' => false, 'error' => $reason]);
})->with([
    'a missing file'     => [fn (): string => test()->directory . '/missing.xml', [], 'file_missing', 'The file :path does not exist.'],
    'a directory'        => [fn (): string => test()->directory, [], 'file_not_regular', 'The path :path is not a regular file.'],
    'an unreadable file' => [function (): string {
        $path = validationFile('<Dfe/>');
        chmod($path, 0o000);

        return $path;
    }, [], 'file_unreadable', 'The file :path cannot be read.'],
    'a file above 5 MiB' => [
        fn (): string => validationFile(str_repeat(' ', ValidateCommand::MAX_BYTES) . '<Dfe/>'),
        [],
        'file_too_large',
        'The file :path is larger than 5242880 bytes.',
    ],
    'an unknown profile' => [fn (): string => validationFile('<Dfe/>'), ['--profile' => 'xades'], 'profile_invalid', 'The profile must be enveloped or detached.'],
]);

it('accepts a file of exactly 5 MiB', function (): void {
    $xml = S::sign(S::unsigned())->xml;

    artisan('efatura:validate', ['path' => validationFile($xml . str_repeat(' ', ValidateCommand::MAX_BYTES - strlen($xml)))])
        ->expectsOutputToContain('The document is valid.')
        ->assertExitCode(0);
});
