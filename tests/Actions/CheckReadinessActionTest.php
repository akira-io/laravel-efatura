<?php

declare(strict_types=1);

use Akira\Efatura\Actions\CheckReadinessAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\RefreshTokenRepository;
use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Enums\ReadinessStatus;
use Akira\Efatura\OAuth\RefreshTokenAccessTokenProvider;
use Akira\Efatura\Readiness\ReadinessReport;
use Akira\Efatura\Readiness\ReadinessResult;
use Akira\Efatura\Tests\Support\CertificateFixtures;
use Akira\Efatura\Tests\Support\InMemoryRefreshTokens;
use Akira\Efatura\Tests\Support\OAuthFixtures as O;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\SequenceFixtures;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function platformSettings(): array
{
    return [
        'efatura.driver'                          => 'platform',
        'efatura.emitter.tax_id'                  => O::EMITTER,
        'efatura.transmitter.oauth.client_id'     => O::CLIENT_ID,
        'efatura.transmitter.oauth.client_secret' => 'x',
    ];
}

function readiness(array $config = [], bool $remote = false): ReadinessReport
{
    config()->set($config);
    app()->forgetInstance(EfaturaConfig::class);
    if (! $remote) {
        Http::fake();
    }

    return resolve(CheckReadinessAction::class)->handle($remote);
}

function readinessOutcome(ReadinessReport $report, ReadinessCheck $check): array
{
    return array_map(fn (ReadinessResult $result): array => [$result->status, $result->code, $result->field], $report->of($check));
}

function platformReadiness(): array
{
    app()->instance(RefreshTokenRepository::class, new InMemoryRefreshTokens([O::EMITTER => 'refresh-' . test()->secret]));

    return [
        'efatura.driver'                          => 'platform',
        'efatura.emitter.tax_id'                  => O::EMITTER,
        'efatura.transmitter.oauth.client_id'     => O::CLIENT_ID,
        'efatura.transmitter.oauth.client_secret' => 'secret-' . test()->secret,
        'efatura.cache.store'                     => 'array',
        'app.key'                                 => 'base64:' . base64_encode(random_bytes(32)),
    ];
}

beforeEach(function (): void {
    P::configure(config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    SequenceFixtures::migrate();
    $this->secret = bin2hex(random_bytes(12));
});

it('reports a complete installation ready without touching the network', function (): void {
    $report = readiness();

    expect($report->ready())->toBeTrue()
        ->and($report->warnings())->toBe([])
        ->and(array_map(fn (ReadinessResult $result): array => [$result->check, $result->status, $result->required], $report->results))->toBe([
            [ReadinessCheck::Configuration, ReadinessStatus::Passed, true],
            [ReadinessCheck::Certificate, ReadinessStatus::Passed, true],
            [ReadinessCheck::Sequences, ReadinessStatus::Passed, true],
            [ReadinessCheck::Environment, ReadinessStatus::Passed, true],
            [ReadinessCheck::Endpoint, ReadinessStatus::Skipped, false],
            [ReadinessCheck::Clock, ReadinessStatus::Skipped, false],
            [ReadinessCheck::Credentials, ReadinessStatus::Skipped, false],
        ])
        ->and($report->of(ReadinessCheck::Certificate)[0]->context)->toBe(['days_left' => 4838]);
    Http::assertNothingSent();
});

it('fails closed on every local gap', function (?Closure $arrange, array $config, ReadinessCheck $check, array $outcome): void {
    $arrange?->__invoke();

    $report = readiness($config);

    expect($report->ready())->toBeFalse()
        ->and(readinessOutcome($report, $check))->toBe($outcome);
    Http::assertNothingSent();
})->with([
    'no transmitter' => [null, ['efatura.transmitter.tax_id' => null], ReadinessCheck::Configuration, [
        [ReadinessStatus::Failed, 'configuration.missing', 'efatura.transmitter.tax_id'],
    ]],
    'no software' => [null, ['efatura.software.code' => null, 'efatura.software.version' => null], ReadinessCheck::Configuration, [
        [ReadinessStatus::Failed, 'configuration.missing', 'efatura.software.code'],
        [ReadinessStatus::Failed, 'configuration.missing', 'efatura.software.version'],
    ]],
    'no middleware key' => [null, ['efatura.transmitter.middleware_key' => null], ReadinessCheck::Configuration, [
        [ReadinessStatus::Failed, 'configuration.missing', 'efatura.transmitter.middleware_key'],
    ]],
    'an expired certificate' => [function (): void {
        CertificateFixtures::store('pem', P::expired());
    }, [], ReadinessCheck::Certificate, [
        [ReadinessStatus::Failed, 'certificate.expired', 'efatura.certificates.certificate_path'],
    ]],
    'no certificate' => [null, ['efatura.certificates.certificate_path' => null], ReadinessCheck::Certificate, [
        [ReadinessStatus::Failed, 'certificate.missing', 'efatura.certificates.certificate_path'],
    ]],
    'no sequences table' => [SequenceFixtures::rollback(...), [], ReadinessCheck::Sequences, [
        [ReadinessStatus::Failed, 'readiness.sequences_table_missing', 'efatura.database.sequences_table'],
    ]],
    'an unknown sequences connection' => [null, ['efatura.database.connection' => 'missing'], ReadinessCheck::Sequences, [
        [ReadinessStatus::Failed, 'readiness.unexpected_failure', null],
    ]],
    'a platform without a refresh token repository' => [null, platformSettings(), ReadinessCheck::Configuration, [
        [ReadinessStatus::Failed, 'transport.missing_credentials', 'efatura.transmitter.oauth'],
    ]],
    'a platform without a refresh token' => [fn (): InMemoryRefreshTokens => app()->instance(RefreshTokenRepository::class, new InMemoryRefreshTokens), platformSettings(), ReadinessCheck::Configuration, [
        [ReadinessStatus::Failed, 'oauth.refresh_token_missing', 'efatura.emitter.tax_id'],
    ]],
    'a platform without emitter or client' => [null, ['efatura.driver' => 'platform'], ReadinessCheck::Configuration, [
        [ReadinessStatus::Failed, 'configuration.missing', 'efatura.emitter.tax_id'],
        [ReadinessStatus::Failed, 'configuration.missing', 'efatura.transmitter.oauth.client_id'],
        [ReadinessStatus::Failed, 'configuration.missing', 'efatura.transmitter.oauth.client_secret'],
    ]],
]);

it('reports a configuration that cannot load and skips every check that needs it', function (bool $remote): void {
    Http::fake();

    $report = readiness(['efatura.transmitter.middleware_key' => 'short-' . test()->secret], $remote);

    expect($report->ready())->toBeFalse()
        ->and(readinessOutcome($report, ReadinessCheck::Configuration))->toBe([[ReadinessStatus::Failed, 'configuration.invalid_length', 'efatura.transmitter.middleware_key']])
        ->and(collect($report->results)->skip(1)->every(fn (ReadinessResult $result): bool => $result->status === ReadinessStatus::Skipped))->toBeTrue()
        ->and($report->of(ReadinessCheck::Certificate)[0]->required)->toBeTrue()
        ->and($report->of(ReadinessCheck::Endpoint)[0]->required)->toBe($remote)
        ->and(json_encode($report))->not->toContain(test()->secret);
    Http::assertNothingSent();
})->with(['local' => [false], 'remote' => [true]]);

it('warns without withholding readiness', function (?Closure $arrange, array $config, ReadinessCheck $check, array $outcome): void {
    $arrange?->__invoke();

    $report = readiness($config);

    expect($report->ready())->toBeTrue()
        ->and(readinessOutcome($report, $check))->toBe($outcome);
})->with([
    'a certificate ten days from its end' => [
        function (): void {
            CertificateFixtures::store('pem', CertificateFixtures::issue(validTo: '2026-10-12T14:00:00Z'));
        },
        [],
        ReadinessCheck::Certificate,
        [[ReadinessStatus::Warning, 'readiness.certificate_expiring', 'efatura.certificates.certificate_path']],
    ],
    'production without a certificate chain' => [null, ['efatura.environment' => 'production'], ReadinessCheck::Certificate, [
        [ReadinessStatus::Warning, 'readiness.certificate_chain_unverified', 'efatura.certificates.ca_bundle_path'],
    ]],
    'tls verification disabled' => [null, ['efatura.http.middleware.verify_tls' => false], ReadinessCheck::Environment, [
        [ReadinessStatus::Warning, 'readiness.tls_verification_disabled', 'efatura.http.middleware.verify_tls'],
    ]],
]);

it('reaches the configured endpoint and compares clocks only when asked to', function (?string $date, ReadinessStatus $status, string $code): void {
    Http::fake(['https://localhost:3443/v1/*' => Http::response('', 404, array_filter(['Date' => $date]))]);

    $report = readiness(remote: true);

    expect(readinessOutcome($report, ReadinessCheck::Endpoint))->toBe([[ReadinessStatus::Passed, 'readiness.endpoint_reachable', null]])
        ->and(readinessOutcome($report, ReadinessCheck::Clock))->toBe([[$status, $code, null]])
        ->and(readinessOutcome($report, ReadinessCheck::Credentials))->toBe([[ReadinessStatus::Skipped, 'readiness.skipped', null]])
        ->and($report->ready())->toBe($status !== ReadinessStatus::Failed);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://localhost:3443/v1/'
        && ! $request->hasHeader('cv-ef-mw-core-transmitter-key'));
})->with([
    'a synchronized clock' => ['Fri, 02 Oct 2026 13:01:00 GMT', ReadinessStatus::Passed, 'readiness.clock_synchronized'],
    'ten minutes apart'    => ['Fri, 02 Oct 2026 13:10:00 GMT', ReadinessStatus::Warning, 'readiness.clock_skew'],
    'two hours ahead'      => ['Fri, 02 Oct 2026 15:00:00 GMT', ReadinessStatus::Failed, 'readiness.clock_skew'],
    'no date header'       => [null, ReadinessStatus::Warning, 'readiness.clock_unverified'],
    'a malformed date'     => ['yesterday', ReadinessStatus::Warning, 'readiness.clock_unverified'],
]);

it('fails an endpoint it cannot reach and skips the clock it would have read', function (): void {
    Http::fake(fn (Request $request): PromiseInterface => Create::rejectionFor(new ConnectException('cURL error 7: Failed to connect', $request->toPsrRequest())));

    $report = readiness(['efatura.http.retry_delay_milliseconds' => 0], remote: true);

    expect($report->ready())->toBeFalse()
        ->and(readinessOutcome($report, ReadinessCheck::Endpoint))->toBe([[ReadinessStatus::Failed, 'transport.connection_failed', null]])
        ->and(readinessOutcome($report, ReadinessCheck::Clock))->toBe([[ReadinessStatus::Skipped, 'readiness.skipped', null]])
        ->and($report->of(ReadinessCheck::Clock)[0]->required)->toBeTrue();
});

it('obtains a platform token when asked to and keeps every secret out of the report', function (Closure $grant, ReadinessStatus $status, string $code): void {
    CarbonImmutable::setTestNow('2026-10-02T13:00:00Z');
    $config = platformReadiness();
    app()->bind(AccessTokenProvider::class, RefreshTokenAccessTokenProvider::class);
    Http::fake([
        'https://services.efatura.cv/*' => Http::response('', 404, ['Date' => 'Fri, 02 Oct 2026 13:00:00 GMT']),
        O::DISCOVERY_URL                => O::discovery(),
        O::TOKEN_URL                    => $grant,
    ]);

    $report = readiness($config, remote: true);

    expect(readinessOutcome($report, ReadinessCheck::Credentials))->toBe([[$status, $code, null]])
        ->and($report->ready())->toBe($status === ReadinessStatus::Passed)
        ->and(json_encode($report))->not->toContain(test()->secret)->not->toContain('access-');
})->with([
    'a granted token' => [fn (): PromiseInterface => O::grant('access-' . test()->secret), ReadinessStatus::Passed, 'readiness.token_obtained'],
    'a refused token' => [fn (): PromiseInterface => Http::response(['error' => 'invalid_grant'], 400), ReadinessStatus::Failed, 'oauth.token_refused'],
]);

it('fails a remote token check when no provider can be bound', function (): void {
    Http::fake(['*' => Http::response('', 404, ['Date' => 'Fri, 02 Oct 2026 13:00:00 GMT'])]);

    $config = platformReadiness();
    app()->offsetUnset(RefreshTokenRepository::class);

    $report = readiness($config, remote: true);

    expect(readinessOutcome($report, ReadinessCheck::Credentials))->toBe([[ReadinessStatus::Failed, 'transport.missing_credentials', 'efatura.transmitter.oauth']]);
});
