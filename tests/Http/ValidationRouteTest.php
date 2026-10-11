<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Exceptions\OfficialArtifactException;
use Akira\Efatura\Tests\Support\DiagnosticsGuard;
use Akira\Efatura\Tests\Support\RouteFixtures;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

function postXml(?UploadedFile $file, array $fields = []): TestResponse
{
    return test()->post('/efatura/validate', [...($file instanceof UploadedFile ? ['file' => $file] : []), ...$fields], RouteFixtures::headers());
}

beforeEach(function (): void {
    RouteFixtures::enable();
    Http::fake();
});

afterEach(function (): void {
    Http::assertNothingSent();
});

it('answers 200 with the validation of a signed document', function (): void {
    $signed = S::sign(S::unsigned(), SignatureProfile::InternallyDetached);

    postXml(UploadedFile::fake()->createWithContent('document.xml', $signed->xml))
        ->assertOk()
        ->assertExactJson([
            'valid'     => true,
            'profile'   => 'detached',
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

it('answers 422 with the failures of an invalid document', function (string $xml, array $fields, array $expected): void {
    postXml(UploadedFile::fake()->createWithContent('document.xml', $xml), $fields)
        ->assertUnprocessable()
        ->assertJson(['valid' => false, 'trust_chain_verified' => false, ...$expected]);
})->with([
    'a tampered amount' => [
        fn (): string => str_replace('<PayableAmount>115</PayableAmount>', '<PayableAmount>116</PayableAmount>', S::sign(S::unsigned())->xml),
        [],
        ['signature' => ['valid' => false, 'failures' => ['signature.digest_mismatch']]],
    ],
    'an unsigned document' => [fn (): string => S::unsigned('FTE'), [], ['schema' => ['valid' => true], 'signature' => ['failures' => ['signature.missing']]]],
    'the wrong profile'    => [
        fn (): string => S::sign(S::unsigned(), SignatureProfile::InternallyDetached)->xml,
        ['profile' => 'enveloped'],
        ['profile' => 'enveloped', 'schema' => ['valid' => false, 'code' => 'xml.schema_invalid'], 'signature' => null],
    ],
    'an xxe attempt' => [
        '<?xml version="1.0"?><!DOCTYPE Dfe [<!ENTITY x SYSTEM "file:///etc/passwd">]><Dfe>&x;</Dfe>',
        [],
        ['profile' => null, 'signature' => null, 'schema' => ['code' => 'xml.doctype_forbidden']],
    ],
]);

it('rejects an upload it cannot validate', function (Closure $file, array $fields, string $field): void {
    postXml($file(), $fields)
        ->assertUnprocessable()
        ->assertJsonPath('valid', false)
        ->assertJsonValidationErrors([$field])
        ->assertJsonMissingPath('schema');
})->with([
    'a missing file'       => [fn (): null => null, [], 'file'],
    'a file above 5 MiB'   => [fn (): UploadedFile => UploadedFile::fake()->create('document.xml', 5121, 'application/xml'), [], 'file'],
    'a pdf'                => [fn (): UploadedFile => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'), [], 'file'],
    'an unknown profile'   => [fn (): UploadedFile => UploadedFile::fake()->createWithContent('document.xml', '<Dfe/>'), ['profile' => 'xades'], 'profile'],
    'a text field as file' => [fn (): null => null, ['file' => '<Dfe/>'], 'file'],
]);

it('answers in json even when the client does not ask for it', function (): void {
    $this->post('/efatura/validate', [], [DiagnosticsGuard::HEADER => DiagnosticsGuard::TOKEN])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('applies the configured upload limit', function (): void {
    RouteFixtures::enable(['max_upload_kilobytes' => 1]);

    postXml(UploadedFile::fake()->createWithContent('document.xml', S::sign(S::unsigned())->xml))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('keeps only the code of a package exception and never its message', function (): void {
    app()->instance(SchemaValidator::class, new class implements SchemaValidator
    {
        public function validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped): void
        {
            throw new ConfigurationException('configuration.invalid_type', 'efatura.certificates.passphrase');
        }
    });

    $response = postXml(UploadedFile::fake()->createWithContent('document.xml', S::sign(S::unsigned())->xml));

    $response->assertUnprocessable()->assertJsonPath('schema', ['valid' => false, 'code' => 'configuration.invalid_type', 'violations' => [], 'violations_total' => 0]);
    expect($response->getContent())->not->toContain('passphrase')->not->toContain('ConfigurationException')->not->toContain('trace');
});

it('answers 503 when the bundled official artifacts cannot be used', function (): void {
    app()->instance(SchemaValidator::class, new class implements SchemaValidator
    {
        public function validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped): void
        {
            throw new OfficialArtifactException('artifacts.invalid_schema', 'validate');
        }
    });

    postXml(UploadedFile::fake()->createWithContent('document.xml', S::sign(S::unsigned())->xml))
        ->assertServiceUnavailable()
        ->assertExactJson(['valid' => false, 'error' => 'artifacts_unavailable', 'code' => 'artifacts.invalid_schema']);
});

it('leaves exceptions from outside the package to the application handler', function (): void {
    $this->withoutExceptionHandling();
    app()->instance(SchemaValidator::class, new class implements SchemaValidator
    {
        public function validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped): void
        {
            throw new LogicException('unexpected');
        }
    });

    expect(fn (): TestResponse => postXml(UploadedFile::fake()->createWithContent('document.xml', S::sign(S::unsigned())->xml)))
        ->toThrow(LogicException::class, 'unexpected');
});
