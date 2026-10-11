<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\ExchangeRatesConfig;
use Akira\Efatura\Configuration\LoadExchangeRatesConfig;
use Akira\Efatura\Enums\ExchangeRateSource;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\TreasuryExchangeRates;

function exchangeRatesConfig(array $section): ExchangeRatesConfig
{
    return ConfigFixtures::load(['exchange_rates' => $section])->exchangeRates;
}

it('selects the BCV buy rate of the same day by default', function (): void {
    $config = ConfigFixtures::load()->exchangeRates;

    expect($config)->toEqual(new ExchangeRatesConfig)
        ->and($config->provider)->toBe(ExchangeRateSource::Bcv)
        ->and($config->bcvRateType)->toBe(ExchangeRateType::Buy)
        ->and($config->bcvMaxPublicationAgeDays)->toBe(0)
        ->and($config->worldBankEconomies)->toBe([])
        ->and($config->fixedRates)->toBe([])
        ->and($config->fixedRateType)->toBe(ExchangeRateType::Custom)
        ->and($config->fixedName)->toBe('Fixed rate')
        ->and($config->callback)->toBeNull();
});

it('reads every option of the exchange rate section', function (): void {
    $config = exchangeRatesConfig([
        'provider'   => ' Fixed ',
        'bcv'        => ['rate_type' => 'SELL', 'max_publication_age_days' => '3'],
        'world_bank' => ['economies' => ['EUR' => 'EMU']],
        'fixed'      => ['rates' => ['EUR' => '110.265', 'USD' => 100], 'rate_type' => 'reference', 'name' => 'Treasury rate'],
        'callback'   => TreasuryExchangeRates::class,
    ]);

    expect($config->provider)->toBe(ExchangeRateSource::Fixed)
        ->and($config->bcvRateType)->toBe(ExchangeRateType::Sell)
        ->and($config->bcvMaxPublicationAgeDays)->toBe(3)
        ->and($config->worldBankEconomies)->toBe(['EUR' => 'EMU'])
        ->and($config->fixedRates)->toBe(['EUR' => '110.265', 'USD' => '100'])
        ->and($config->fixedRateType)->toBe(ExchangeRateType::Reference)
        ->and($config->fixedName)->toBe('Treasury rate')
        ->and($config->callback)->toBe(TreasuryExchangeRates::class);
});

it('accepts provider and rate type enum cases', function (): void {
    $config = exchangeRatesConfig(['provider' => ExchangeRateSource::WorldBank, 'bcv' => ['rate_type' => ExchangeRateType::Sell]]);

    expect($config->provider)->toBe(ExchangeRateSource::WorldBank)
        ->and($config->bcvRateType)->toBe(ExchangeRateType::Sell);
});

it('refuses an exchange rate section it cannot use', function (array $section, string $code, string $field): void {
    expect(fn (): ExchangeRatesConfig => exchangeRatesConfig($section))->toThrow(function (ConfigurationException $exception) use ($code, $field): void {
        expect($exception->errorCode)->toBe($code)
            ->and($exception->field)->toBe($field);
    });
})->with([
    'an unknown provider'        => [['provider' => 'ecb'], 'configuration.invalid_exchange_rate_provider', 'efatura.exchange_rates.provider'],
    'a numeric provider'         => [['provider' => 1], 'configuration.invalid_exchange_rate_provider', 'efatura.exchange_rates.provider'],
    'a BCV reference rate'       => [['bcv' => ['rate_type' => 'reference']], 'configuration.invalid_rate_type', 'efatura.exchange_rates.bcv.rate_type'],
    'an unknown fixed rate'      => [['fixed' => ['rate_type' => 'average']], 'configuration.invalid_rate_type', 'efatura.exchange_rates.fixed.rate_type'],
    'a negative age'             => [['bcv' => ['max_publication_age_days' => -1]], 'configuration.invalid_integer', 'efatura.exchange_rates.bcv.max_publication_age_days'],
    'a list of economies'        => [['world_bank' => ['economies' => ['EMU']]], 'configuration.invalid_type', 'efatura.exchange_rates.world_bank.economies'],
    'an economy path'            => [['world_bank' => ['economies' => ['EUR' => '../x']]], 'configuration.invalid_type', 'efatura.exchange_rates.world_bank.economies'],
    'economies not a map'        => [['world_bank' => ['economies' => 'EUR:EMU']], 'configuration.invalid_type', 'efatura.exchange_rates.world_bank.economies'],
    'a zero fixed rate'          => [['fixed' => ['rates' => ['EUR' => '0']]], 'configuration.invalid_type', 'efatura.exchange_rates.fixed.rates'],
    'a float fixed rate'         => [['fixed' => ['rates' => ['EUR' => 110.265]]], 'configuration.invalid_type', 'efatura.exchange_rates.fixed.rates'],
    'a fixed rate without code'  => [['fixed' => ['rates' => ['110.265']]], 'configuration.invalid_type', 'efatura.exchange_rates.fixed.rates'],
    'an empty fixed name'        => [['fixed' => ['name' => ' ']], 'configuration.empty_string', 'efatura.exchange_rates.fixed.name'],
    'an unknown callback class'  => [['callback' => 'App\Missing'], 'configuration.invalid_type', 'efatura.exchange_rates.callback'],
    'a callback provider alone'  => [['provider' => 'callback'], 'configuration.invalid_type', 'efatura.exchange_rates.callback'],
    'a scalar bcv section'       => [['bcv' => 'buy'], 'configuration.invalid_type', 'efatura.exchange_rates.bcv'],
    'a four letter fixed code'   => [['fixed' => ['rates' => ['EURO' => '110.265']]], 'configuration.invalid_type', 'efatura.exchange_rates.fixed.rates'],
    'an unofficial fixed code'   => [['fixed' => ['rates' => ['AAA' => '110.265']]], 'configuration.invalid_type', 'efatura.exchange_rates.fixed.rates'],
    'an unofficial economy code' => [['world_bank' => ['economies' => ['AAA' => 'EMU']]], 'configuration.invalid_type', 'efatura.exchange_rates.world_bank.economies'],
    'a four letter economy'      => [['world_bank' => ['economies' => ['EUR' => 'EMUS']]], 'configuration.invalid_type', 'efatura.exchange_rates.world_bank.economies'],
    'CVE outside Cabo Verde'     => [['world_bank' => ['economies' => ['CVE' => 'USA']]], 'configuration.invalid_type', 'efatura.exchange_rates.world_bank.economies'],
]);

it('normalizes the currency and economy codes and accepts CVE restated as CPV', function (): void {
    $config = exchangeRatesConfig([
        'world_bank' => ['economies' => [' eur ' => ' emu ', 'cve' => 'cpv']],
        'fixed'      => ['rates' => ['usd' => '100']],
    ]);

    expect($config->worldBankEconomies)->toBe(['EUR' => 'EMU', 'CVE' => 'CPV'])
        ->and($config->fixedRates)->toBe(['USD' => '100']);
});

it('loads the section on its own from the host repository', function (): void {
    $config = new LoadExchangeRatesConfig(ConfigFixtures::hostRepository(['exchange_rates' => ['provider' => 'world_bank']]))();

    expect($config->provider)->toBe(ExchangeRateSource::WorldBank);
});
