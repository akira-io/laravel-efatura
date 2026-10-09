<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Enums\DecimalViolation;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Str;

final class DecimalFormatter
{
    private const string PLAIN_DECIMAL = '/^-?[0-9]+(?:\.[0-9]+)?$/D';

    /**
     * @phpstan-assert-if-true =int|string|BigDecimal $value
     */
    public static function isPlainDecimal(mixed $value): bool
    {
        return \is_int($value)
            || $value instanceof BigDecimal
            || (\is_string($value) && Str::isMatch(self::PLAIN_DECIMAL, $value));
    }

    public static function fitsScale(BigDecimal $value, int $scale): bool
    {
        self::checkScale($scale);

        return $value->strippedOfTrailingZeros()->getScale() <= $scale;
    }

    public static function fitsIntegerDigits(BigDecimal $value): bool
    {
        return $value->getPrecision() - $value->getScale() <= Fiscal::INTEGER_DIGITS;
    }

    public static function violation(mixed $value, ?int $maxScale = null): ?DecimalViolation
    {
        return self::isPlainDecimal($value) ? self::boundsViolation(BigDecimal::of($value), $maxScale) : DecimalViolation::InvalidDecimal;
    }

    public static function parse(mixed $value, ?int $maxScale = null, string $field = 'amount'): BigDecimal
    {
        if ($maxScale !== null) {
            self::checkScale($maxScale);
        }

        if (! self::isPlainDecimal($value)) {
            throw self::exception(DecimalViolation::InvalidDecimal, $field);
        }

        $decimal   = BigDecimal::of($value);
        $violation = self::boundsViolation($decimal, $maxScale);

        if ($violation instanceof DecimalViolation) {
            throw self::exception($violation, $field);
        }

        return $decimal;
    }

    public static function decimal(BigDecimal $value, int $maxScale = Fiscal::AMOUNT_SCALE, string $field = 'amount'): string
    {
        self::ensureScale($value, $maxScale, $field);

        $plain = (string) $value;

        return str_contains($plain, '.') ? rtrim(rtrim($plain, '0'), '.') : $plain;
    }

    public static function money(Money $value, int $scale = 2, bool $round = true, string $field = 'amount'): string
    {
        self::checkScale($scale);

        if (! $round) {
            self::ensureScale($value->getAmount(), $scale, $field);
        }

        return (string) $value->getAmount()->toScale($scale, $round ? self::fiscalRounding() : RoundingMode::Unnecessary);
    }

    public static function fiscalRounding(): RoundingMode
    {
        return RoundingMode::HalfUp;
    }

    private static function boundsViolation(BigDecimal $value, ?int $maxScale): ?DecimalViolation
    {
        if (! self::fitsIntegerDigits($value)) {
            return DecimalViolation::IntegerDigitsExceeded;
        }

        return $maxScale === null || self::fitsScale($value, $maxScale) ? null : DecimalViolation::ScaleExceeded;
    }

    private static function ensureScale(BigDecimal $value, int $scale, string $field): void
    {
        if (! self::fitsScale($value, $scale)) {
            throw self::exception(DecimalViolation::ScaleExceeded, $field);
        }
    }

    private static function exception(DecimalViolation $violation, string $field): EfaturaValidationException
    {
        return match ($violation) {
            DecimalViolation::InvalidDecimal        => EfaturaValidationException::invalidDecimal($field),
            DecimalViolation::IntegerDigitsExceeded => EfaturaValidationException::integerDigitsExceeded($field),
            DecimalViolation::ScaleExceeded         => EfaturaValidationException::decimalScaleExceeded($field),
        };
    }

    private static function checkScale(int $scale): void
    {
        if ($scale < 0) {
            throw DefinitionException::negativeScale($scale);
        }
    }
}
