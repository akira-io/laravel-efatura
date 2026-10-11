<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Currency\CachedExchangeRateProvider;
use Akira\Efatura\Currency\CallbackExchangeRateProvider;
use Akira\Efatura\Currency\ExchangeRateProviderResolver;
use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\FixedExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Akira\Efatura\Tests\Support\TreasuryExchangeRates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-07-21T14:30:00Z');
});

function exchangeRatesUsing(array $section, array $cache = []): ExchangeRateProvider
{
    app()->instance(EfaturaConfig::class, ConfigFixtures::load([
        'exchange_rates' => $section,
        'cache'          => ['store' => 'array', 'exchange_rates_ttl_seconds' => 600, ...$cache],
        'http'           => ['retries' => 0],
    ]));

    return resolve(ExchangeRateProvider::class);
}

function cachedQuoteEntry(array $overrides = []): array
{
    return [
        'sourceCurrency' => 'EUR', 'targetCurrency' => 'CVE', 'rate' => '110.265', 'rateType' => 'buy',
        'effectiveAt'    => '2026-07-21T00:00:00-01:00', 'retrievedAt' => '2026-07-21T00:00:00-01:00',
        'provider'       => 'BCV', 'sourceUrl' => null, 'evidence' => [], ...$overrides,
    ];
}

function bcvPublishes(): void
{
    Http::fake(['https://www.bcv.cv/*' => Http::response(C::fixture('bcv-rates-2026-07-21.html'))]);
}

it('binds the configured provider to the contract', function (array $section, string $class): void {
    expect(exchangeRatesUsing($section))->toBeInstanceOf($class)
        ->and(resolve(ExchangeRateProviderResolver::class)->provider())->toBeInstanceOf($class);
})->with([
    'the BCV by default' => [[], CachedExchangeRateProvider::class],
    'the World Bank'     => [['provider' => 'world_bank'], CachedExchangeRateProvider::class],
    'fixed rates'        => [['provider' => 'fixed', 'fixed' => ['rates' => ['EUR' => '110.265']]], FixedExchangeRateProvider::class],
    'a callback class'   => [['provider' => 'callback', 'callback' => TreasuryExchangeRates::class], CallbackExchangeRateProvider::class],
]);

it('passes the configured options to the providers', function (): void {
    bcvPublishes();

    expect(exchangeRatesUsing(['bcv' => ['rate_type' => 'sell']])->rate(C::request(null, 'JPY'))->rate->__toString())->toBe('0.59854')
        ->and(exchangeRatesUsing(['provider' => 'fixed', 'fixed' => ['rates' => ['EUR' => '110.265'], 'name' => 'Treasury']])->rate(C::request(null))->provider)
        ->toBe('Treasury')
        ->and(exchangeRatesUsing(['provider' => 'callback', 'callback' => TreasuryExchangeRates::class])->rate(C::request(null))->provider)
        ->toBe('Treasury');
});

it('accepts an earlier BCV publication when the configured age allows it', function (): void {
    bcvPublishes();

    $quote = exchangeRatesUsing(['bcv' => ['max_publication_age_days' => 2]])->rate(C::request(effectiveAt: '2026-07-23T12:00:00Z'));

    expect($quote->effectiveAt->toDateString())->toBe('2026-07-21');
});

it('reads the World Bank with the configured economies', function (): void {
    Http::fake([
        'https://api.worldbank.org/v2/country/CPV/*' => Http::response(C::fixture('world-bank-cpv.json')),
        'https://api.worldbank.org/v2/country/EMU/*' => Http::response(C::fixture('world-bank-emu.json')),
    ]);

    $previous = exchangeRatesUsing(['provider' => 'world_bank', 'world_bank' => ['economies' => ['EUR' => 'EMU']]])->rate(C::request(null));
    $request  = exchangeRatesUsing(['provider' => 'world_bank', 'world_bank' => ['economies' => ['EUR' => 'EMU'], 'observation_year' => 'request']])
        ->rate(C::request(null, effectiveAt: '2025-07-21T12:00:00Z'));

    expect((string) $previous->rate)->toBe('110.19006')
        ->and($previous->evidence['observation_year'])->toBe('previous')
        ->and($request->evidence['observation_year'])->toBe('request');
});

it('refuses a callback class that cannot be called', function (): void {
    expect(fn (): ExchangeRateProvider => exchangeRatesUsing(['provider' => 'callback', 'callback' => stdClass::class]))
        ->toThrow(function (ConfigurationException $exception): void {
            expect($exception->errorCode)->toBe('configuration.invalid_type')
                ->and($exception->field)->toBe('efatura.exchange_rates.callback');
        });
});

it('caches a quote per provider, pair, type and Cabo Verde day', function (): void {
    bcvPublishes();
    $provider = exchangeRatesUsing([]);

    $first  = $provider->rate(C::request(null));
    $second = $provider->rate(C::request(null, effectiveAt: '2026-07-21T20:00:00Z'));
    $provider->rate(C::request(null, 'JPY'));
    $provider->rate(C::request());

    expect($second)->toEqual($first)
        ->and(Cache::store('array')->get('efatura:exchange_rates:bcv:EUR:CVE:default:2026-07-21'))->toBeArray()
        ->and(Cache::store('array')->has('efatura:exchange_rates:bcv:EUR:CVE:buy:2026-07-21'))->toBeTrue();
    Http::assertSentCount(3);
});

it('answers from a cached entry without asking', function (): void {
    Http::fake();
    Cache::store('array')->put('efatura:exchange_rates:bcv:EUR:CVE:buy:2026-07-21', cachedQuoteEntry(['evidence' => ['indicator' => 'BCV']]), 600);

    expect(exchangeRatesUsing([])->rate(C::request())->provider)->toBe('BCV');
    Http::assertNothingSent();
});

it('asks again once the cached quote expires', function (): void {
    bcvPublishes();
    $provider = exchangeRatesUsing([], ['exchange_rates_ttl_seconds' => 60]);

    $provider->rate(C::request());
    CarbonImmutable::setTestNow('2026-07-21T14:31:01Z');
    $provider->rate(C::request());

    Http::assertSentCount(2);
});

it('checks a cached quote against the request it answers', function (): void {
    bcvPublishes();
    $provider = exchangeRatesUsing(['bcv' => ['max_publication_age_days' => 5]]);
    $provider->rate(C::request(effectiveAt: '2026-07-23T12:00:00Z'));

    Cache::store('array')->put('efatura:exchange_rates:bcv:EUR:CVE:buy:2026-07-20', Cache::store('array')->get('efatura:exchange_rates:bcv:EUR:CVE:buy:2026-07-23'), 600);

    expect(C::failure(fn (): ExchangeRateQuote => $provider->rate(C::request(effectiveAt: '2026-07-20T12:00:00Z'))))->toBe(ExchangeRateFailure::DateUnavailable);
});

it('asks again when the cached entry is not a quote it can rebuild', function (mixed $poisoned): void {
    bcvPublishes();
    Cache::store('array')->put('efatura:exchange_rates:bcv:EUR:CVE:buy:2026-07-21', $poisoned, 600);

    expect((string) exchangeRatesUsing([])->rate(C::request())->rate)->toBe('110.26500');
    Http::assertSentCount(1);
})->with([
    'a string'         => ['110.265'],
    'a partial array'  => [['rate' => '1']],
    'a zero rate'      => [cachedQuoteEntry(['rate' => '0'])],
    'a numeric url'    => [cachedQuoteEntry(['sourceUrl' => 1])],
    'not an array'     => [new ArrayObject(cachedQuoteEntry())],
    'numeric evidence' => [cachedQuoteEntry(['evidence' => ['indicator' => 1]])],
    'listed evidence'  => [cachedQuoteEntry(['evidence' => ['PA.NUS.FCRF']])],
]);
