<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Brick\Money\Context\CustomContext;
use Brick\Money\Exception\MoneyException;
use Brick\Money\Money;
use Illuminate\Support\Str;

final class FiscalMoney
{
    public static function cve(int|float|string|Money $amount, string $field = 'amount'): Money
    {
        return self::of($amount, Fiscal::CURRENCY, $field);
    }

    public static function of(int|float|string|Money $amount, string $currency, string $field = 'amount'): Money
    {
        return self::create($amount, $currency, 2, true, $field);
    }

    public static function exact(int|float|string|Money $amount, string $currency, int $scale = Fiscal::AMOUNT_SCALE, string $field = 'amount'): Money
    {
        return self::create($amount, $currency, $scale, false, $field);
    }

    private static function create(int|float|string|Money $amount, string $currency, int $scale, bool $round, string $field): Money
    {
        if ($scale < 0 || $scale > Fiscal::AMOUNT_SCALE) {
            throw DefinitionException::moneyScale($scale, Fiscal::AMOUNT_SCALE);
        }

        if (\is_float($amount)) {
            throw EfaturaValidationException::invalidMoney($field);
        }

        if (! Str::isMatch('/^[A-Z]{3}$/D', $currency)) {
            throw EfaturaValidationException::invalidCurrency($field);
        }

        if ($amount instanceof Money && $amount->getCurrency()->getCurrencyCode() !== $currency) {
            throw EfaturaValidationException::currencyMismatch($field);
        }

        $decimal = DecimalFormatter::parse(
            $amount instanceof Money ? $amount->getAmount() : $amount,
            $round ? null : $scale,
            $field,
        );

        try {
            return Money::of(
                $decimal,
                $currency,
                new CustomContext($scale),
                $round ? DecimalFormatter::fiscalRounding() : RoundingMode::Unnecessary,
            );
        } catch (MathException|MoneyException) {
            throw EfaturaValidationException::invalidMoney($field);
        }
    }
}
