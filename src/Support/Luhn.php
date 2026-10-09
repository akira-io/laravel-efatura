<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Exceptions\DefinitionException;
use Illuminate\Support\Str;

final class Luhn
{
    public static function checkDigit(string $digits): int
    {
        self::assertDigits($digits);

        $sum = collect(str_split(strrev($digits)))
            ->map(static fn (string $digit, int $position): int => (int) $digit * ($position % 2 === 0 ? 2 : 1))
            ->sum(static fn (int $product): int => intdiv($product, 10) + $product % 10);

        return $sum * 9 % 10;
    }

    public static function passes(string $digitsWithCheckDigit): bool
    {
        self::assertDigits($digitsWithCheckDigit);

        return self::checkDigit(substr($digitsWithCheckDigit, 0, -1)) === (int) substr($digitsWithCheckDigit, -1);
    }

    private static function assertDigits(string $digits): void
    {
        if (! Str::isMatch('/\A[0-9]+\z/', $digits)) {
            throw DefinitionException::luhnPayload($digits);
        }
    }
}
