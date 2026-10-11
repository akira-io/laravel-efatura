<?php

declare(strict_types=1);

use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\ExchangeRateRequest;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

it('normalizes the currencies of a request and targets CVE by default', function (): void {
    $request = new ExchangeRateRequest(' eur ', CarbonImmutable::parse('2026-07-21T12:00:00Z'));

    expect($request->sourceCurrency)->toBe('EUR')
        ->and($request->targetCurrency)->toBe('CVE')
        ->and($request->rateType)->toBeNull();
});

it('refuses a currency outside the official catalog', function (string $currency): void {
    expect(C::failure(fn (): ExchangeRateRequest => new ExchangeRateRequest($currency, CarbonImmutable::now())))
        ->toBe(ExchangeRateFailure::CurrencyUnsupported);
})->with(['empty' => [''], 'two letters' => ['EU'], 'four letters' => ['EURO'], 'digits' => ['12A'], 'unknown' => ['AAA']]);

it('refuses a target currency outside the official catalog', function (): void {
    expect(C::failure(fn (): ExchangeRateRequest => new ExchangeRateRequest('EUR', CarbonImmutable::now(), targetCurrency: 'AAA')))
        ->toBe(ExchangeRateFailure::CurrencyUnsupported);
});

it('keeps a quote rate at five decimals rounded half up', function (string|int|BigDecimal $rate, string $expected): void {
    expect((string) C::quote(['rate' => $rate])->rate)->toBe($expected);
})->with([
    'more decimals'  => ['110.2654351', '110.26544'],
    'half up'        => ['0.000005', '0.00001'],
    'an integer'     => [1, '1.00000'],
    'a big decimal'  => [BigDecimal::of('0.59727'), '0.59727'],
    'fewer decimals' => ['110.265', '110.26500'],
]);

it('refuses a rate that is not a positive decimal or rounds to zero', function (string|int $rate): void {
    expect(C::failure(fn (): ExchangeRateQuote => C::quote(['rate' => $rate])))->toBe(ExchangeRateFailure::RateInvalid);
})->with([
    'zero'               => ['0'],
    'negative'           => [-1],
    'not a number'       => ['abc'],
    'rounds to zero'     => ['0.000004'],
    'too many integers'  => ['1000000000000000'],
    'an exponent'        => ['1e2'],
    'a huge exponent'    => ['1e300000000'],
    'surrounding space'  => [' 110.265'],
    'over 32 characters' => ['110.' . str_repeat('0', 29)],
]);

it('accepts a plain decimal of exactly 32 characters', function (): void {
    expect((string) C::quote(['rate' => '110.' . str_repeat('0', 28)])->rate)->toBe('110.00000');
});

it('normalizes the quote currencies and provider', function (): void {
    $quote = C::quote(['sourceCurrency' => 'eur', 'targetCurrency' => ' cve', 'provider' => ' BCV ']);

    expect($quote->sourceCurrency)->toBe('EUR')
        ->and($quote->targetCurrency)->toBe('CVE')
        ->and($quote->provider)->toBe('BCV');
});

it('refuses a quote without a provider name', function (): void {
    expect(C::failure(fn (): ExchangeRateQuote => C::quote(['provider' => '  '])))->toBe(ExchangeRateFailure::ResponseInvalid);
});

it('accepts a quote without a source url', function (): void {
    expect(C::quote(['sourceUrl' => null])->sourceUrl)->toBeNull();
});

it('refuses a source url that is not plain https without leaking it', function (string $url): void {
    $exception = C::exception(fn (): ExchangeRateQuote => C::quote(['sourceUrl' => $url]));

    expect($exception->failure)->toBe(ExchangeRateFailure::SourceRequired)
        ->and($exception->getMessage())->not->toContain('SECRET')
        ->and($exception->context)->toBe([])
        ->and($exception->getPrevious())->toBeNull();
})->with([
    'empty'       => [''],
    'plain http'  => ['http://www.bcv.cv/rates'],
    'not a url'   => ['not a url'],
    'no host'     => ['https:///rates'],
    'credentials' => ['https://user:SECRET@www.bcv.cv/rates'],
    'a fragment'  => ['https://www.bcv.cv/rates#SECRET'],
]);

it('accepts a source url with a query', function (): void {
    expect(C::quote(['sourceUrl' => 'https://www.bcv.cv/rates?_expType=PDF'])->sourceUrl)->toBe('https://www.bcv.cv/rates?_expType=PDF');
});

it('keeps evidence of where a rate came from', function (): void {
    $evidence = ['indicator' => 'PA.NUS.FCRF', 'target_url' => 'https://api.worldbank.org/v2/country/CPV'];

    expect(C::quote(['evidence' => $evidence])->evidence)->toBe($evidence);
});

it('refuses evidence that is empty or carries a credential url', function (array $evidence, ExchangeRateFailure $failure): void {
    expect(C::failure(fn (): ExchangeRateQuote => C::quote(['evidence' => $evidence])))->toBe($failure);
})->with([
    'an empty value'   => [['indicator' => ' '], ExchangeRateFailure::ResponseInvalid],
    'a numeric value'  => [['indicator' => 1], ExchangeRateFailure::ResponseInvalid],
    'a listed value'   => [['PA.NUS.FCRF'], ExchangeRateFailure::ResponseInvalid],
    'an empty key'     => [['' => 'PA.NUS.FCRF'], ExchangeRateFailure::ResponseInvalid],
    'a credential url' => [['source_url' => 'https://user:SECRET@api.worldbank.org'], ExchangeRateFailure::SourceRequired],
    'a plain http url' => [['source_url' => 'http://api.worldbank.org'], ExchangeRateFailure::SourceRequired],
]);

it('answers a request for the same pair, type and an earlier or equal date', function (?ExchangeRateType $type): void {
    $quote = C::quote();

    expect($quote->answering(C::request($type)))->toBe($quote);
})->with(['the same type' => [ExchangeRateType::Buy], 'any type' => [null]]);

it('refuses to answer a request for another pair or type', function (array $overrides): void {
    expect(C::failure(fn (): ExchangeRateQuote => C::quote($overrides)->answering(C::request())))->toBe(ExchangeRateFailure::PairMismatch);
})->with([
    'another source' => [['sourceCurrency' => 'USD']],
    'another target' => [['targetCurrency' => 'USD']],
    'another type'   => [['rateType' => ExchangeRateType::Sell]],
]);

it('refuses to answer a request dated before the quote', function (): void {
    $quote = C::quote(['effectiveAt' => CarbonImmutable::parse('2026-07-21T12:00:01Z')]);

    expect(C::failure(fn (): ExchangeRateQuote => $quote->answering(C::request())))->toBe(ExchangeRateFailure::DateUnavailable);
});

it('builds the identity quote for CVE', function (): void {
    $effectiveAt = CarbonImmutable::parse('2026-07-21T12:00:00Z');
    $quote       = ExchangeRateQuote::identity($effectiveAt, CarbonImmutable::parse('2026-07-21T13:00:00Z'));

    expect($quote->sourceCurrency)->toBe('CVE')
        ->and($quote->targetCurrency)->toBe('CVE')
        ->and((string) $quote->rate)->toBe('1.00000')
        ->and($quote->rateType)->toBe(ExchangeRateType::Reference)
        ->and($quote->provider)->toBe('identity')
        ->and($quote->effectiveAt)->toBe($effectiveAt)
        ->and($quote->sourceUrl)->toBeNull()
        ->and($quote->evidence)->toBe([]);
});

it('reports exchange rate failures through the package base with stable codes', function (ExchangeRateFailure $failure, string $code, bool $retryable): void {
    $exception = ExchangeRateException::because($failure, ['provider' => 'bcv']);

    expect($exception)->toBeInstanceOf(EfaturaException::class)
        ->and($exception->errorCode)->toBe($code)
        ->and($exception->getMessage())->toBe($code)
        ->and($exception->context)->toBe(['provider' => 'bcv'])
        ->and($exception->retryable)->toBe($retryable);
})->with([
    [ExchangeRateFailure::ProviderUnavailable, 'exchange_rate.provider_unavailable', true],
    [ExchangeRateFailure::ResponseInvalid, 'exchange_rate.response_invalid', false],
    [ExchangeRateFailure::CurrencyUnsupported, 'exchange_rate.currency_unsupported', false],
    [ExchangeRateFailure::PairMismatch, 'exchange_rate.pair_mismatch', false],
    [ExchangeRateFailure::RateInvalid, 'exchange_rate.rate_invalid', false],
    [ExchangeRateFailure::DateUnavailable, 'exchange_rate.date_unavailable', false],
    [ExchangeRateFailure::DateInvalid, 'exchange_rate.date_invalid', false],
    [ExchangeRateFailure::Stale, 'exchange_rate.stale', false],
    [ExchangeRateFailure::SourceRequired, 'exchange_rate.source_required', false],
    [ExchangeRateFailure::InvoiceInvalid, 'exchange_rate.invoice_invalid', false],
    [ExchangeRateFailure::AlternativesConflict, 'exchange_rate.alternatives_conflict', false],
]);
