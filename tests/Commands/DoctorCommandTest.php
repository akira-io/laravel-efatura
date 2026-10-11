<?php

declare(strict_types=1);

use Akira\Efatura\Commands\DoctorCommand;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Tests\Support\CertificateFixtures;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\SequenceFixtures;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

function doctorConfiguration(array $config = []): void
{
    config()->set($config);
    app()->forgetInstance(EfaturaConfig::class);
}

function doctorJson(array $options = []): array
{
    $exitCode = Artisan::call('efatura:doctor', ['--json' => true, ...$options]);
    $output   = Artisan::output();

    return [$exitCode, json_decode($output, true, flags: JSON_THROW_ON_ERROR), $output];
}

beforeEach(function (): void {
    P::configure(config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    SequenceFixtures::migrate();
});

it('declares its signature and description with Laravel command attributes', function (): void {
    $command = new ReflectionClass(DoctorCommand::class);

    expect($command->getAttributes(Signature::class)[0]->newInstance()->signature)->toStartWith('efatura:doctor')
        ->and($command->getAttributes(Description::class)[0]->newInstance()->description)->toBe('Check whether this installation can issue e-Fatura documents')
        ->and(resolve(DoctorCommand::class)->getDefinition()->getOptions())->toHaveKeys(['remote', 'json']);
});

it('reports a ready installation offline and exits successfully', function (): void {
    Http::fake();

    artisan('efatura:doctor')
        ->expectsOutputToContain('readiness.configured')
        ->expectsOutputToContain('readiness.sequences_table_present')
        ->expectsOutputToContain('Remote checks were skipped. Run with --remote to reach the configured endpoint.')
        ->expectsOutputToContain('This installation is ready to issue documents.')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

it('keeps a ready installation with warnings successful', function (): void {
    doctorConfiguration(['efatura.http.middleware.verify_tls' => false]);

    artisan('efatura:doctor')
        ->expectsOutputToContain('readiness.tls_verification_disabled | efatura.http.middleware.verify_tls')
        ->expectsOutputToContain('This installation is ready to issue documents, with 1 warning(s).')
        ->assertExitCode(0);
});

it('fails when a check blocks the installation', function (array $options): void {
    SequenceFixtures::rollback();
    Http::fake(['*' => Http::response('', 404, ['Date' => 'Fri, 02 Oct 2026 13:00:00 GMT'])]);

    artisan('efatura:doctor', $options)
        ->expectsOutputToContain('readiness.sequences_table_missing')
        ->expectsOutputToContain('This installation is not ready to issue documents.')
        ->assertExitCode(1);
})->with([
    'offline' => [[]],
    'remote'  => [['--remote' => true]],
]);

it('reaches the configured endpoint only with the remote option', function (): void {
    Http::fake(['https://localhost:3443/v1/*' => Http::response('', 404, ['Date' => 'Fri, 02 Oct 2026 13:00:00 GMT'])]);

    artisan('efatura:doctor', ['--remote' => true])
        ->expectsOutputToContain('readiness.endpoint_reachable')
        ->expectsOutputToContain('readiness.clock_synchronized')
        ->doesntExpectOutputToContain('Remote checks were skipped.')
        ->expectsOutputToContain('This installation is ready to issue documents.')
        ->assertExitCode(0);

    Http::assertSentCount(1);
});

it('prints a stable json document', function (): void {
    Http::fake();

    [$exitCode, $document, $output] = doctorJson();

    expect($exitCode)->toBe(0)
        ->and(array_keys($document))->toBe(['ready', 'checks'])
        ->and($document['ready'])->toBeTrue()
        ->and(array_column($document['checks'], 'check'))
        ->toBe(['configuration', 'certificate', 'sequences', 'environment', 'endpoint', 'clock', 'credentials'])
        ->and($document['checks'][0])->toBe([
            'check'    => 'configuration',
            'status'   => 'passed',
            'code'     => 'readiness.configured',
            'field'    => null,
            'required' => true,
            'context'  => ['driver' => 'middleware'],
        ])
        ->and($document['checks'][4])->toBe([
            'check'    => 'endpoint',
            'status'   => 'skipped',
            'code'     => 'readiness.skipped',
            'field'    => null,
            'required' => false,
            'context'  => [],
        ])
        ->and($output)->toContain('"context": {}')->not->toContain('"context": []');
    Http::assertNothingSent();
});

it('prints the json document of a failing installation and exits with a failure', function (): void {
    doctorConfiguration(['efatura.software.code' => null]);

    [$exitCode, $document] = doctorJson();

    expect($exitCode)->toBe(1)
        ->and($document['ready'])->toBeFalse()
        ->and($document['checks'][0])->toMatchArray(['check' => 'configuration', 'status' => 'failed', 'code' => 'configuration.missing', 'field' => 'efatura.software.code']);
});

it('never prints secrets, tokens, passphrases or private key paths', function (array $certificate, int $expectedExit): void {
    $secret = bin2hex(random_bytes(16));
    CertificateFixtures::store('pem-encrypted', config: $certificate);
    doctorConfiguration([
        'efatura.transmitter.middleware_key'      => 'middleware-' . $secret . str_repeat('m', 21),
        'efatura.transmitter.oauth.client_secret' => 'client-secret-' . $secret,
        'efatura.transmitter.oauth.client_id'     => 'client-id-' . $secret,
    ]);

    [$exitCode, , $json] = doctorJson();
    $humanExitCode       = Artisan::call('efatura:doctor');
    $human               = Artisan::output();

    expect($exitCode)->toBe($expectedExit)
        ->and($humanExitCode)->toBe($expectedExit)
        ->and($human)->toContain('certificate');

    foreach ([$json, $human] as $output) {
        expect($output)->not->toContain($secret)
            ->not->toContain(CertificateFixtures::PASSPHRASE)
            ->not->toContain(trim(CertificateFixtures::PASSPHRASE))
            ->not->toContain($certificate['private_key_path'] ?? 'signer.key')
            ->not->toContain('BEGIN');
    }
})->with([
    'a ready installation'                   => [[], 0],
    'a missing private key at a hidden path' => [['private_key_path' => 'hidden-keys/emitter-private.key'], 1],
]);
