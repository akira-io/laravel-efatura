<?php

declare(strict_types=1);

use Akira\Efatura\Actions\CheckReadinessAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\RefreshTokenRepository;
use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Enums\ReadinessStatus;
use Akira\Efatura\Readiness\ReadinessReport;
use Akira\Efatura\Readiness\ReadinessResult;
use Akira\Efatura\Tests\Support\CertificateFixtures;
use Akira\Efatura\Tests\Support\InMemoryRefreshTokens;
use Akira\Efatura\Tests\Support\OAuthFixtures as O;
use Akira\Efatura\Tests\Support\PreparationFixtures as P;
use Akira\Efatura\Tests\Support\RecordingAccessTokens;
use Akira\Efatura\Tests\Support\SequenceFixtures;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

function checkedReadiness(array $config = [], bool $remote = true): ReadinessReport
{
    config()->set(['efatura.http.retries' => 2, 'efatura.http.retry_delay_milliseconds' => 0, ...$config]);
    app()->forgetInstance(EfaturaConfig::class);

    return resolve(CheckReadinessAction::class)->handle($remote);
}

function checkedOutcome(ReadinessReport $report, ReadinessCheck $check): array
{
    return array_map(fn (ReadinessResult $result): array => [$result->status, $result->code], $report->of($check));
}

function platformChecks(): array
{
    return [
        'efatura.driver'                          => 'platform',
        'efatura.emitter.tax_id'                  => O::EMITTER,
        'efatura.transmitter.oauth.client_id'     => O::CLIENT_ID,
        'efatura.transmitter.oauth.client_secret' => 'client-secret',
        'efatura.cache.store'                     => 'array',
        'app.key'                                 => 'base64:' . base64_encode(random_bytes(32)),
    ];
}

function platformDate(): array
{
    return ['Date' => CarbonImmutable::now('UTC')->format('D, d M Y H:i:s \G\M\T')];
}

beforeEach(function (): void {
    P::configure(config: ['efatura.transmitter.middleware_key' => str_repeat('k', 64)]);
    SequenceFixtures::migrate();
});

it('retries the endpoint check as a read and passes once it answers', function (): void {
    Http::fake(['*' => Http::sequence()->push('', 503)->push('', 503)->push('', 404, platformDate())]);

    $report = checkedReadiness();

    expect(checkedOutcome($report, ReadinessCheck::Endpoint))->toBe([[ReadinessStatus::Passed, 'readiness.endpoint_reachable']]);
    Http::assertSentCount(3);
});

it('warns about an endpoint that still fails on the server after the retries', function (int $status): void {
    Http::fake(['*' => Http::response('', $status, platformDate())]);

    $report = checkedReadiness();

    expect(checkedOutcome($report, ReadinessCheck::Endpoint))->toBe([[ReadinessStatus::Warning, 'readiness.endpoint_unavailable']])
        ->and($report->of(ReadinessCheck::Endpoint)[0]->context)->toBe(['status' => $status])
        ->and($report->ready())->toBeTrue();
})->with([500, 503]);

it('draws the clock thresholds at five minutes and one hour, ahead or behind', function (int $skew, ReadinessStatus $status): void {
    Http::fake(['*' => Http::response('', 404, ['Date' => CarbonImmutable::now('UTC')->addSeconds($skew)->format('D, d M Y H:i:s \G\M\T')])]);

    expect(checkedOutcome(checkedReadiness(), ReadinessCheck::Clock)[0][0])->toBe($status);
})->with([
    'five minutes ahead'          => [300, ReadinessStatus::Passed],
    'one second more'             => [301, ReadinessStatus::Warning],
    'five minutes and one behind' => [-301, ReadinessStatus::Warning],
    'one hour ahead'              => [3600, ReadinessStatus::Warning],
    'one hour and one second'     => [3601, ReadinessStatus::Failed],
    'one hour and one behind'     => [-3601, ReadinessStatus::Failed],
]);

it('warns about a certificate from the moment fewer than thirty whole days remain', function (string $validTo, ReadinessStatus $status, int $daysLeft): void {
    CertificateFixtures::store('pem', CertificateFixtures::issue(validTo: $validTo));

    $result = checkedReadiness(remote: false)->of(ReadinessCheck::Certificate)[0];

    expect($result->status)->toBe($status)
        ->and($result->context)->toBe(['days_left' => $daysLeft]);
})->with([
    'thirty days'      => ['2026-11-01T13:00:00Z', ReadinessStatus::Passed, 30],
    'one second short' => ['2026-11-01T12:59:59Z', ReadinessStatus::Warning, 29],
]);

it('asks for a platform token of the configured emitter with the create scope', function (): void {
    $tokens = new RecordingAccessTokens('readiness');
    app()->instance(RefreshTokenRepository::class, new InMemoryRefreshTokens([O::EMITTER => 'refresh']));
    app()->instance(AccessTokenProvider::class, $tokens);
    Http::fake(['*' => Http::response('', 404, platformDate())]);

    $report = checkedReadiness(platformChecks());

    expect(checkedOutcome($report, ReadinessCheck::Credentials))->toBe([[ReadinessStatus::Passed, 'readiness.token_obtained']])
        ->and($tokens->calls)->toBe([['token', O::EMITTER, ['cv_ef_dfe_create']]]);
});

it('builds the token provider from a refresh token repository bound after the package booted', function (): void {
    app()->instance(RefreshTokenRepository::class, new InMemoryRefreshTokens([O::EMITTER => 'refresh']));
    Http::fake(['https://services.efatura.cv/*' => Http::response('', 404, platformDate()), O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => O::grant('granted')]);

    $report = checkedReadiness(platformChecks());

    expect(app()->bound(AccessTokenProvider::class))->toBeFalse()
        ->and(checkedOutcome($report, ReadinessCheck::Credentials))->toBe([[ReadinessStatus::Passed, 'readiness.token_obtained']]);
});
