<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Money\CatalogCurrency;
use Illuminate\Support\Str;

final readonly class CurrencyCode
{
    public static function normalize(string $code): string
    {
        $normalized = Str::upper(Str::trim($code));

        if (preg_match('/\A[A-Z]{3}\z/', $normalized) !== 1 || ! CatalogCurrency::isOfficial($normalized)) {
            throw ExchangeRateException::because(ExchangeRateFailure::CurrencyUnsupported);
        }

        return $normalized;
    }
}
