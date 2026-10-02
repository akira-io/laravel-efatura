<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Brick\Math\Exception\MathException;
use Brick\Money\Context\CustomContext;
use Brick\Money\Exception\MoneyException;
use Brick\Money\Money;
use Illuminate\Support\Str;

final class FiscalMoney
{
    public static function cve(int|string|Money $amount): Money
    {
        return self::of($amount, 'CVE');
    }

    public static function of(int|string|Money $amount, string $currency): Money
    {
        return self::create($amount, $currency, 2, true);
    }

    public static function exact(int|string|Money $amount, string $currency, int $scale = 5): Money
    {
        return self::create($amount, $currency, $scale, false);
    }

    private static function create(int|string|Money $amount, string $currency, int $scale, bool $round): Money
    {
        if (! Str::isMatch('/^[A-Z]{3}$/D', $currency)) {
            throw new EfaturaValidationException('currency', __('efatura::efatura.validation.invalid_currency'));
        }

        if ($scale < 0 || $scale > 5) {
            throw new EfaturaValidationException('scale', __('efatura::efatura.validation.invalid_money_scale'));
        }

        if ($amount instanceof Money && $amount->getCurrency()->getCurrencyCode() !== $currency) {
            throw new EfaturaValidationException('currency', __('efatura::efatura.validation.currency_mismatch'));
        }

        $decimal = DecimalFormatter::parse(
            $amount instanceof Money ? $amount->getAmount() : $amount,
            $round ? null : $scale,
        );

        try {
            return Money::of(
                $decimal,
                $currency,
                new CustomContext($scale),
                DecimalFormatter::roundingMode($round),
            );
        } catch (MathException|MoneyException) {
            throw new EfaturaValidationException('amount', __('efatura::efatura.validation.invalid_money'));
        }
    }
}
