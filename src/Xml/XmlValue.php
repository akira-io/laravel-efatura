<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class XmlValue
{
    public static function decimal(BigDecimal|Money|null $value, string $path, int $scale = Fiscal::AMOUNT_SCALE): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return DecimalFormatter::decimal($value instanceof Money ? $value->getAmount() : $value, $scale, $path);
        } catch (EfaturaValidationException $efaturaValidationException) {
            throw ValidationException::withMessages([$path => $efaturaValidationException->getMessage()]);
        }
    }

    public static function date(?CarbonInterface $moment, bool $instant = false): ?string
    {
        return $moment instanceof CarbonInterface ? Fiscal::format($moment, Fiscal::DATE_FORMAT, $instant) : null;
    }

    public static function time(?CarbonInterface $moment, bool $instant = false): ?string
    {
        return $moment instanceof CarbonInterface ? Fiscal::format($moment, Fiscal::TIME_FORMAT, $instant) : null;
    }

    public static function dateTime(?CarbonInterface $moment, bool $instant = false): ?string
    {
        return $moment instanceof CarbonInterface ? Fiscal::format($moment, Fiscal::DATE_TIME_FORMAT, $instant) : null;
    }

    public static function boolean(?bool $value): ?string
    {
        return $value === null ? null : var_export($value, true);
    }

    public static function integer(?int $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
