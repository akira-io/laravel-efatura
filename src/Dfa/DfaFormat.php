<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use Akira\Efatura\Money\DecimalFormatter;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Illuminate\Support\Str;

final readonly class DfaFormat
{
    public static function cve(Money $amount): string
    {
        return self::grouped($amount->getAmount()->toScale(2, DecimalFormatter::fiscalRounding()), '$');
    }

    public static function foreign(Money $amount): string
    {
        $value = BigDecimal::of(DecimalFormatter::decimal($amount->getAmount(), $amount->getAmount()->getScale()));
        $scale = max($value->getScale(), $amount->getCurrency()->getDefaultFractionDigits());

        return self::grouped($value->toScale($scale), ',') . ' ' . $amount->getCurrency()->getCurrencyCode();
    }

    public static function rate(BigDecimal $rate, string $currency): string
    {
        return '1 ' . $currency . ' = ' . self::grouped($rate->toScale(5, DecimalFormatter::fiscalRounding()), ',') . ' CVE';
    }

    public static function quantity(BigDecimal $quantity): string
    {
        return Str::replace('.', ',', DecimalFormatter::decimal($quantity, $quantity->getScale()));
    }

    public static function percentage(BigDecimal $percentage): string
    {
        return self::quantity($percentage) . '%';
    }

    private static function grouped(BigDecimal $value, string $separator): string
    {
        $digits   = (string) $value->abs();
        $integer  = Str::before($digits, '.');
        $fraction = Str::contains($digits, '.') ? $separator . Str::after($digits, '.') : '';
        $grouped  = Str::reverse(implode(' ', mb_str_split(Str::reverse($integer), 3)));

        return ($value->isNegative() ? '-' : '') . $grouped . $fraction;
    }
}
