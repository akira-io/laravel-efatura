<?php

declare(strict_types=1);

use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\ExchangeRateRequest;
use Akira\Efatura\Currency\WorldBankExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const WORLD_BANK_CPV = 'https://api.worldbank.org/v2/country/CPV/indicator/PA.NUS.FCRF?format=json&date=2025&per_page=1';
const WORLD_BANK_EMU = 'https://api.worldbank.org/v2/country/EMU/indicator/PA.NUS.FCRF?format=json&date=2025&per_page=1';

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-07-21T14:30:00Z');
});

function worldBankServes(?string $cpv = null, ?string $emu = null): void
{
    Http::fake([
        'https://api.worldbank.org/v2/country/CPV/*' => Http::response($cpv ?? C::fixture('world-bank-cpv.json')),
        'https://api.worldbank.org/v2/country/EMU/*' => Http::response($emu ?? C::fixture('world-bank-emu.json')),
    ]);
}

function worldBankRequest(string $source = 'USD', string $target = 'CVE', ?ExchangeRateType $type = null): ExchangeRateRequest
{
    return new ExchangeRateRequest($source, CarbonImmutable::parse('2025-07-21T00:00:00Z'), $type, $target);
}

it('returns the CVE per USD annual average with its provenance', function (): void {
    worldBankServes();

    $quote = C::worldBank()->rate(worldBankRequest());

    expect((string) $quote->rate)->toBe('101.74950')
        ->and($quote->rateType)->toBe(ExchangeRateType::Reference)
        ->and($quote->effectiveAt->toIso8601String())->toBe('2025-01-01T00:00:00-01:00')
        ->and($quote->retrievedAt->toIso8601String())->toBe('2026-07-21T13:30:00-01:00')
        ->and($quote->provider)->toBe('World Bank PA.NUS.FCRF')
        ->and($quote->sourceUrl)->toBe('https://data.worldbank.org/indicator/PA.NUS.FCRF')
        ->and($quote->evidence)->toBe([
            'source'             => 'World Bank',
            'indicator'          => 'PA.NUS.FCRF',
            'observation_period' => '2025',
            'source_currency'    => 'USD',
            'source_value'       => '1',
            'target_currency'    => 'CVE',
            'target_economy'     => 'CPV',
            'target_value'       => '101.7495',
            'target_url'         => WORLD_BANK_CPV,
        ]);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === WORLD_BANK_CPV && $request->header('Accept') === ['application/json']);
});

it('crosses EUR to CVE through USD with observations of the same year', function (): void {
    worldBankServes();

    $quote = C::worldBank()->rate(worldBankRequest('EUR', type: ExchangeRateType::Reference));

    expect((string) $quote->rate)->toBe('110.19006')
        ->and($quote->evidence)->toMatchArray([
            'source_currency' => 'EUR',
            'source_economy'  => 'EMU',
            'source_value'    => '0.9234',
            'source_url'      => WORLD_BANK_EMU,
        ]);
    Http::assertSentCount(2);
});

it('answers USD to USD without asking the World Bank', function (): void {
    Http::fake();

    expect((string) C::worldBank()->rate(worldBankRequest('USD', 'USD'))->rate)->toBe('1.00000');
    Http::assertNothingSent();
});

it('reads the observation of the Cabo Verde year at the UTC year boundary', function (): void {
    worldBankServes();

    $request = new ExchangeRateRequest('USD', CarbonImmutable::parse('2026-01-01T00:30:00Z'));

    expect(C::worldBank()->rate($request)->evidence['observation_period'])->toBe('2025');
});

it('refuses another rate type or an unmapped currency before any request', function (ExchangeRateRequest $request, ExchangeRateFailure $failure): void {
    Http::fake();

    expect(C::failure(fn (): ExchangeRateQuote => C::worldBank(['economies' => []])->rate($request)))->toBe($failure);
    Http::assertNothingSent();
})->with([
    'a buy rate'       => [fn (): ExchangeRateRequest => worldBankRequest(type: ExchangeRateType::Buy), ExchangeRateFailure::PairMismatch],
    'a sell rate'      => [fn (): ExchangeRateRequest => worldBankRequest(type: ExchangeRateType::Sell), ExchangeRateFailure::PairMismatch],
    'a custom rate'    => [fn (): ExchangeRateRequest => worldBankRequest(type: ExchangeRateType::Custom), ExchangeRateFailure::PairMismatch],
    'an unmapped EUR'  => [fn (): ExchangeRateRequest => worldBankRequest('EUR'), ExchangeRateFailure::CurrencyUnsupported],
    'an unmapped goal' => [fn (): ExchangeRateRequest => worldBankRequest('USD', 'EUR'), ExchangeRateFailure::CurrencyUnsupported],
]);

it('refuses an economy map that would misattribute an observation', function (array $economies, ExchangeRateFailure $failure): void {
    expect(C::failure(fn (): WorldBankExchangeRateProvider => C::worldBank(['economies' => $economies])))->toBe($failure);
})->with([
    'CVE mapped elsewhere' => [['CVE' => 'EMU'], ExchangeRateFailure::ResponseInvalid],
    'an empty economy'     => [['EUR' => ' '], ExchangeRateFailure::ResponseInvalid],
    'a path economy'       => [['EUR' => 'EMU/../x'], ExchangeRateFailure::ResponseInvalid],
    'an unknown currency'  => [['AAA' => 'EMU'], ExchangeRateFailure::CurrencyUnsupported],
]);

it('accepts the CVE mapping restated and normalizes the map', function (): void {
    worldBankServes();

    $quote = C::worldBank(['economies' => [' eur ' => 'emu', 'CVE' => 'CPV']])->rate(worldBankRequest('EUR'));

    expect($quote->evidence['source_economy'])->toBe('EMU');
});

it('refuses a base url outside the World Bank api', function (string $url): void {
    expect(C::failure(fn (): WorldBankExchangeRateProvider => C::worldBank(http: ['base_url' => $url])))->toBe(ExchangeRateFailure::SourceRequired);
})->with([
    'another host' => ['https://rates.example/api'],
    'another port' => ['https://api.worldbank.org:8443'],
]);

it('accepts the official api as a configured base url', function (): void {
    worldBankServes();

    expect(C::worldBank(http: ['base_url' => 'https://api.worldbank.org'])->rate(worldBankRequest())->sourceUrl)
        ->toBe('https://data.worldbank.org/indicator/PA.NUS.FCRF');
});

it('reports an observation the World Bank does not have without falling back', function (Closure $cpv): void {
    worldBankServes($cpv());

    expect(C::failure(fn (): ExchangeRateQuote => C::worldBank()->rate(worldBankRequest())))->toBe(ExchangeRateFailure::DateUnavailable);
})->with([
    'a null value'       => [fn (): string => str_replace('"value": 101.7495', '"value": null', C::fixture('world-bank-cpv.json'))],
    'no value'           => [fn (): string => str_replace('"value": 101.7495,', '', C::fixture('world-bank-cpv.json'))],
    'no observation'     => [fn (): string => '[{"page": 1}, []]'],
    'a null observation' => [fn (): string => '[{"page": 1}, [null]]'],
    'another year'       => [fn (): string => str_replace('"date": "2025"', '"date": "2024"', C::fixture('world-bank-cpv.json'))],
]);

it('refuses a response it cannot trust without keeping its content', function (Closure $cpv): void {
    worldBankServes($cpv());

    $exception = C::exception(fn (): ExchangeRateQuote => C::worldBank()->rate(worldBankRequest()));

    expect($exception->failure)->toBe(ExchangeRateFailure::ResponseInvalid)
        ->and($exception->getMessage())->not->toContain('SECRET')
        ->and($exception->getPrevious())->toBeNull();
})->with([
    'not json'             => [fn (): string => 'SECRET'],
    'a json object'        => [fn (): string => '{"message": "SECRET"}'],
    'no observations list' => [fn (): string => '[{"page": 1}, "SECRET"]'],
    'only metadata'        => [fn (): string => '[{"page": 1}]'],
    'a scalar observation' => [fn (): string => '[{"page": 1}, ["SECRET"]]'],
    'a secret value'       => [fn (): string => str_replace('101.7495', '"SECRET"', C::fixture('world-bank-cpv.json'))],
    'a zero value'         => [fn (): string => str_replace('101.7495', '0', C::fixture('world-bank-cpv.json'))],
    'an exponent value'    => [fn (): string => str_replace('101.7495', '1.0e-12', C::fixture('world-bank-cpv.json'))],
    'another economy'      => [fn (): string => str_replace('"countryiso3code": "CPV"', '"countryiso3code": "EMU"', C::fixture('world-bank-cpv.json'))],
    'another indicator'    => [fn (): string => str_replace('"id": "PA.NUS.FCRF"', '"id": "NY.GDP.MKTP.CD"', C::fixture('world-bank-cpv.json'))],
    'no indicator'         => [fn (): string => str_replace('"indicator": {', '"other": {', C::fixture('world-bank-cpv.json'))],
    'over one mebibyte'    => [fn (): string => C::fixture('world-bank-cpv.json') . str_repeat(' ', 1_048_576)],
    'too deep'             => [fn (): string => str_repeat('[', 40) . str_repeat(']', 40)],
]);

it('accepts an observation written as a decimal string', function (): void {
    worldBankServes(str_replace('101.7495', '"101.7495"', C::fixture('world-bank-cpv.json')));

    expect((string) C::worldBank()->rate(worldBankRequest())->rate)->toBe('101.74950');
});

it('reports the World Bank unavailable', function (Closure $response, array $context): void {
    Http::fake(['https://api.worldbank.org/*' => $response]);

    $exception = C::exception(fn (): ExchangeRateQuote => C::worldBank()->rate(worldBankRequest()));

    expect($exception->failure)->toBe(ExchangeRateFailure::ProviderUnavailable)
        ->and($exception->context)->toBe($context);
})->with([
    'unavailable' => [fn (): PromiseInterface => Http::response('', 503), ['source' => 'world_bank', 'status' => 503]],
    'refused'     => [fn (Request $request): PromiseInterface => Create::rejectionFor(new ConnectException('down', $request->toPsrRequest())), ['source' => 'world_bank']],
]);
