<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\RoundingMode;
use Brick\Money\Context\CustomContext;
use Brick\Money\Currency;
use Brick\Money\Money;

final class FiscalMoney
{
    public static function cve(int|float|string|Money $amount, string $field = 'amount'): Money
    {
        return self::of($amount, Fiscal::CURRENCY, $field);
    }

    public static function of(int|float|string|Money $amount, string|Currency $currency, string $field = 'amount'): Money
    {
        return self::create($amount, $currency, 2, true, $field);
    }

    public static function exact(int|float|string|Money $amount, string|Currency $currency, int $scale = Fiscal::AMOUNT_SCALE, string $field = 'amount'): Money
    {
        return self::create($amount, $currency, $scale, false, $field);
    }

    private static function create(int|float|string|Money $amount, string|Currency $currency, int $scale, bool $round, string $field): Money
    {
        if ($scale < 0 || $scale > Fiscal::AMOUNT_SCALE) {
            throw DefinitionException::moneyScale($scale, Fiscal::AMOUNT_SCALE);
        }

        if (\is_float($amount)) {
            throw EfaturaValidationException::invalidMoney($field);
        }

        $currency = \is_string($currency) ? CatalogCurrency::of($currency, $field) : $currency;
        if ($amount instanceof Money && $amount->getCurrency()->getCurrencyCode() !== $currency->getCurrencyCode()) {
            throw EfaturaValidationException::currencyMismatch($field);
        }

        $decimal = DecimalFormatter::parse(
            $amount instanceof Money ? $amount->getAmount() : $amount,
            $round ? null : $scale,
            $field,
        );

        return Money::of(
            $decimal,
            $currency,
            new CustomContext($scale),
            $round ? DecimalFormatter::fiscalRounding() : RoundingMode::Unnecessary,
        );
    }
}
