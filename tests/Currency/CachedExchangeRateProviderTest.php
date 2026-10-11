<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Currency\CachedExchangeRateProvider;
use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Akira\Efatura\Tests\Support\FailingCacheStore;
use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-07-21T14:30:00Z');
    Http::fake([
        'https://www.bcv.cv/*'                       => Http::response(C::fixture('bcv-rates-2026-07-21.html')),
        'https://api.worldbank.org/v2/country/CPV/*' => Http::response(C::fixture('world-bank-cpv.json')),
    ]);
});

function cachedRatesUsing(array $section): ExchangeRateProvider
{
    app()->instance(EfaturaConfig::class, ConfigFixtures::load([
        'exchange_rates' => $section,
        'cache'          => ['store' => 'array', 'exchange_rates_ttl_seconds' => 600],
        'http'           => ['retries' => 0],
    ]));

    return resolve(ExchangeRateProvider::class);
}

it('keys a quote by the effective rate type and the publication age it was accepted under', function (array $section, ?ExchangeRateType $type, string $key): void {
    cachedRatesUsing($section)->rate(C::request($type));

    expect(Cache::store('array')->get($key))->toBeArray();
})->with([
    'the default buy rate'   => [[], null, 'efatura:exchange_rates:bcv:age_0:EUR:CVE:buy:2026-07-21'],
    'a buy request'          => [[], ExchangeRateType::Buy, 'efatura:exchange_rates:bcv:age_0:EUR:CVE:buy:2026-07-21'],
    'a configured sell rate' => [['bcv' => ['rate_type' => 'sell']], null, 'efatura:exchange_rates:bcv:age_0:EUR:CVE:sell:2026-07-21'],
    'an allowed age'         => [['bcv' => ['max_publication_age_days' => 2]], null, 'efatura:exchange_rates:bcv:age_2:EUR:CVE:buy:2026-07-21'],
]);

it('shares one entry between a request without type and one for the default type', function (): void {
    $provider = cachedRatesUsing([]);

    $provider->rate(C::request(null));
    $provider->rate(C::request(ExchangeRateType::Buy));

    Http::assertSentCount(1);
});

it('never keeps an earlier BCV publication under the day it was asked for', function (): void {
    $provider = cachedRatesUsing(['bcv' => ['max_publication_age_days' => 2]]);

    $provider->rate(C::request(effectiveAt: '2026-07-23T12:00:00Z'));
    $provider->rate(C::request(effectiveAt: '2026-07-23T13:00:00Z'));

    expect(Cache::store('array')->has('efatura:exchange_rates:bcv:age_2:EUR:CVE:buy:2026-07-23'))->toBeFalse();
    Http::assertSentCount(2);
});

it('keeps an annual World Bank observation dated before the request', function (): void {
    $provider = cachedRatesUsing(['provider' => 'world_bank']);

    $provider->rate(C::request(null, 'USD'));
    $provider->rate(C::request(null, 'USD'));

    expect(Cache::store('array')->get('efatura:exchange_rates:world_bank:previous:USD:CVE:reference:2026-07-21'))->toBeArray();
    Http::assertSentCount(1);
});

it('treats a cache store that fails to read or write as a miss', function (): void {
    $store    = new FailingCacheStore;
    $provider = new CachedExchangeRateProvider(C::bcv(), new Repository($store), 'efatura:exchange_rates:bcv:age_0', 600, ExchangeRateType::Buy);

    expect((string) $provider->rate(C::request())->rate)->toBe('110.26500')
        ->and($store->reads)->toBe(1)
        ->and($store->writes)->toBe(1);
});

it('lets an error raised by the cache store surface', function (bool $failsReads, string $message, int $writes): void {
    $store    = new FailingCacheStore(TypeError::class, $failsReads);
    $provider = new CachedExchangeRateProvider(C::bcv(), new Repository($store), 'efatura:exchange_rates:bcv:age_0', 600, ExchangeRateType::Buy);

    expect(fn (): ExchangeRateQuote => $provider->rate(C::request()))->toThrow(TypeError::class, $message)
        ->and($store->reads)->toBe(1)
        ->and($store->writes)->toBe($writes);
})->with([
    'on read'  => [true, 'cache read failed', 0],
    'on write' => [false, 'cache write failed', 1],
]);
