<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Support\Fiscal;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;

final class CatalogCurrency
{
    public static function of(string $code): Currency
    {
        try {
            return Currency::of($code);
        } catch (UnknownCurrencyException) {
            return new Currency($code, 0, $code, Fiscal::AMOUNT_SCALE);
        }
    }
}
