<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Currency\BcvExchangeRateParser;
use Akira\Efatura\Currency\BcvExchangeRateProvider;
use Akira\Efatura\Currency\ExchangeRateClient;
use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\ExchangeRateRequest;
use Akira\Efatura\Currency\WorldBankExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Carbon\CarbonImmutable;
use Closure;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class CurrencyFixtures
{
    public static function request(
        ?ExchangeRateType $type = ExchangeRateType::Buy,
        string $currency = 'EUR',
        string $effectiveAt = '2026-07-21T12:00:00Z',
    ): ExchangeRateRequest {
        return new ExchangeRateRequest($currency, CarbonImmutable::parse($effectiveAt), $type);
    }

    public static function quote(array $overrides = []): ExchangeRateQuote
    {
        return new ExchangeRateQuote(...[
            'sourceCurrency' => 'EUR',
            'targetCurrency' => 'CVE',
            'rate'           => '110.265',
            'rateType'       => ExchangeRateType::Buy,
            'effectiveAt'    => CarbonImmutable::parse('2026-07-21T00:00:00-01:00'),
            'retrievedAt'    => CarbonImmutable::parse('2026-07-21T12:00:00Z'),
            'provider'       => 'Banco de Cabo Verde',
            'sourceUrl'      => 'https://www.bcv.cv/rates',
            ...$overrides,
        ]);
    }

    public static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/currency/' . $name);
    }

    public static function bcv(array $options = [], array $http = []): BcvExchangeRateProvider
    {
        return new BcvExchangeRateProvider(...[
            'client'  => resolve(ExchangeRateClient::class),
            'parser'  => new BcvExchangeRateParser,
            'clock'   => resolve(ClockInterface::class),
            'profile' => ConfigFixtures::load(['http' => ['retries' => 0, 'bcv' => $http]])->http->bcv,
            ...$options,
        ]);
    }

    public static function worldBank(array $options = [], array $http = []): WorldBankExchangeRateProvider
    {
        return new WorldBankExchangeRateProvider(...[
            'client'    => resolve(ExchangeRateClient::class),
            'clock'     => resolve(ClockInterface::class),
            'profile'   => ConfigFixtures::load(['http' => ['retries' => 0, 'world_bank' => $http]])->http->worldBank,
            'economies' => ['EUR' => 'EMU'],
            ...$options,
        ]);
    }

    public static function failure(Closure $operation): ExchangeRateFailure
    {
        return self::exception($operation)->failure;
    }

    public static function exception(Closure $operation): ExchangeRateException
    {
        try {
            $operation();
        } catch (ExchangeRateException $exchangeRateException) {
            return $exchangeRateException;
        }

        throw new RuntimeException('Expected an exchange rate failure.');
    }
}
