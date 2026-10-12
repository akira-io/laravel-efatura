<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\RoundingMode;
use Brick\Money\Money;

final class Amount
{
    public static function format(?Money $money, ?string $currency = null): string
    {
        if ($money === null) {
            return '-';
        }

        return $money->getAmount()->toScale(2, RoundingMode::HALF_UP).' '.($currency ?? $money->getCurrency()->getCurrencyCode());
    }
}
