<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Str;

final class DecimalFormatter
{
    public static function parse(int|float|string|BigDecimal $value, ?int $maxScale = null): BigDecimal
    {
        if (\is_float($value)) {
            throw new EfaturaValidationException('amount', __('efatura::efatura.validation.invalid_decimal'));
        }

        self::checkScale($maxScale);

        if (\is_string($value) && ! Str::isMatch('/^-?[0-9]+(?:\.[0-9]+)?$/D', $value)) {
            throw new EfaturaValidationException('amount', __('efatura::efatura.validation.invalid_decimal'));
        }

        $decimal = BigDecimal::of($value);

        if ($maxScale !== null) {
            try {
                $decimal->toScale($maxScale, RoundingMode::Unnecessary);
            } catch (MathException) {
                throw new EfaturaValidationException('amount', __('efatura::efatura.validation.decimal_scale_exceeded'));
            }
        }

        return $decimal;
    }

    public static function decimal(BigDecimal $value, int $maxScale = 5): string
    {
        self::checkScale($maxScale);

        try {
            $value->toScale($maxScale, RoundingMode::Unnecessary);
        } catch (MathException) {
            throw new EfaturaValidationException('amount', __('efatura::efatura.validation.decimal_scale_exceeded'));
        }

        $plain = (string) $value;

        return str_contains($plain, '.') ? rtrim(rtrim($plain, '0'), '.') : $plain;
    }

    public static function money(Money $value, int $scale = 2, bool $round = true): string
    {
        self::checkScale($scale);

        try {
            return (string) $value->getAmount()->toScale(
                $scale,
                self::roundingMode($round),
            );
        } catch (MathException) {
            throw new EfaturaValidationException('amount', __('efatura::efatura.validation.decimal_scale_exceeded'));
        }
    }

    public static function roundingMode(bool $round): RoundingMode
    {
        return $round ? RoundingMode::HalfUp : RoundingMode::Unnecessary;
    }

    private static function checkScale(?int $scale): void
    {
        if ($scale !== null && $scale < 0) {
            throw new EfaturaValidationException('scale', __('efatura::efatura.validation.invalid_decimal_scale'));
        }
    }
}
