<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use JsonException;

use const JSON_THROW_ON_ERROR;

final readonly class WorldBankObservation
{
    public static function value(string $body, string $economy, int $year): BigDecimal
    {
        try {
            $payload = json_decode($body, true, Fiscal::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw self::invalid();
        }

        if (! \is_array($payload) || ! \is_array($payload[1] ?? null)) {
            throw self::invalid();
        }

        $observation = $payload[1][0] ?? [];

        if (! \is_array($observation)) {
            throw self::invalid();
        }

        if (($observation['value'] ?? null) === null || ($observation['date'] ?? null) !== (string) $year) {
            throw ExchangeRateException::because(ExchangeRateFailure::DateUnavailable, ['source' => 'world_bank']);
        }

        if (($observation['countryiso3code'] ?? null) !== $economy || data_get($observation, 'indicator.id') !== WorldBankExchangeRateProvider::INDICATOR) {
            throw self::invalid();
        }

        $value = \is_float($observation['value']) ? (string) $observation['value'] : $observation['value'];

        if (! DecimalFormatter::isPlainDecimal($value) || ! BigDecimal::of($value)->isPositive()) {
            throw self::invalid();
        }

        return BigDecimal::of($value);
    }

    private static function invalid(): ExchangeRateException
    {
        return ExchangeRateException::because(ExchangeRateFailure::ResponseInvalid, ['source' => 'world_bank']);
    }
}
