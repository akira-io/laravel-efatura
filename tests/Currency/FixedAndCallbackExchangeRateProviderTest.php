<?php

declare(strict_types=1);

use Akira\Efatura\Currency\CallbackExchangeRateProvider;
use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\ExchangeRateRequest;
use Akira\Efatura\Currency\FixedExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Carbon\CarbonImmutable;
use Psr\Clock\ClockInterface;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-07-21T14:30:00Z');
});

function fixedRates(array $rates = ['eur' => '110.265'], array $options = []): FixedExchangeRateProvider
{
    return new FixedExchangeRateProvider(resolve(ClockInterface::class), $rates, ...$options);
}

it('returns the configured rate for the requested date', function (): void {
    $request = C::request(null);
    $quote   = fixedRates(options: ['name' => 'Accounting rate', 'sourceUrl' => 'https://example.com/rates'])->rate($request);

    expect((string) $quote->rate)->toBe('110.26500')
        ->and($quote->rateType)->toBe(ExchangeRateType::Custom)
        ->and($quote->effectiveAt)->toBe($request->effectiveAt)
        ->and($quote->retrievedAt->toIso8601String())->toBe('2026-07-21T13:30:00-01:00')
        ->and($quote->provider)->toBe('Accounting rate')
        ->and($quote->sourceUrl)->toBe('https://example.com/rates');
});

it('answers with the configured rate type', function (): void {
    expect(fixedRates(options: ['rateType' => ExchangeRateType::Reference])->rate(C::request(ExchangeRateType::Reference))->rateType)
        ->toBe(ExchangeRateType::Reference);
});

it('refuses a request it has no rate, pair or type for', function (ExchangeRateRequest $request, ExchangeRateFailure $failure): void {
    expect(C::failure(fn (): ExchangeRateQuote => fixedRates()->rate($request)))->toBe($failure);
})->with([
    'another currency' => [fn (): ExchangeRateRequest => C::request(null, 'USD'), ExchangeRateFailure::CurrencyUnsupported],
    'another target'   => [fn (): ExchangeRateRequest => new ExchangeRateRequest('EUR', CarbonImmutable::now(), targetCurrency: 'USD'), ExchangeRateFailure::PairMismatch],
    'another type'     => [fn (): ExchangeRateRequest => C::request(ExchangeRateType::Buy), ExchangeRateFailure::PairMismatch],
]);

it('refuses a configured rate that is not usable', function (array $rates, array $options, ExchangeRateFailure $failure): void {
    expect(C::failure(fn (): FixedExchangeRateProvider => fixedRates($rates, $options)))->toBe($failure);
})->with([
    'a zero rate'       => [['EUR' => '0'], [], ExchangeRateFailure::RateInvalid],
    'an unknown code'   => [['AAA' => '1'], [], ExchangeRateFailure::CurrencyUnsupported],
    'an empty name'     => [['EUR' => '1'], ['name' => ' '], ExchangeRateFailure::ResponseInvalid],
    'a plain http page' => [['EUR' => '1'], ['sourceUrl' => 'http://example.com'], ExchangeRateFailure::SourceRequired],
]);

it('forwards the request to the callback and checks its answer', function (): void {
    $received = null;
    $provider = new CallbackExchangeRateProvider(function (ExchangeRateRequest $request) use (&$received): ExchangeRateQuote {
        $received = $request;

        return C::quote(['rateType' => ExchangeRateType::Custom]);
    });
    $request = C::request(ExchangeRateType::Custom);

    expect((string) $provider->rate($request)->rate)->toBe('110.26500')
        ->and($received)->toBe($request);
});

it('refuses a callback answer for another request', function (): void {
    $provider = new CallbackExchangeRateProvider(fn (): ExchangeRateQuote => C::quote(['sourceCurrency' => 'USD']));

    expect(C::failure(fn (): ExchangeRateQuote => $provider->rate(C::request())))->toBe(ExchangeRateFailure::PairMismatch);
});

it('refuses a callback answer that is not a quote', function (): void {
    $provider = new CallbackExchangeRateProvider(fn (): string => '110.265');

    expect(C::failure(fn (): ExchangeRateQuote => $provider->rate(C::request())))->toBe(ExchangeRateFailure::ResponseInvalid);
});

it('keeps the exchange rate failures the callback raises', function (): void {
    $failure  = ExchangeRateException::because(ExchangeRateFailure::CurrencyUnsupported);
    $provider = new CallbackExchangeRateProvider(fn (): never => throw $failure);

    expect(C::exception(fn (): ExchangeRateQuote => $provider->rate(C::request())))->toBe($failure);
});

it('reports any other callback failure as the provider being unavailable', function (): void {
    $cause    = new RuntimeException('Network unavailable');
    $provider = new CallbackExchangeRateProvider(fn (): never => throw $cause);

    expect(C::exception(fn (): ExchangeRateQuote => $provider->rate(C::request())))
        ->failure->toBe(ExchangeRateFailure::ProviderUnavailable)
        ->retryable->toBeTrue()
        ->context->toBe(['source' => 'callback'])
        ->getPrevious()->toBe($cause);
});
