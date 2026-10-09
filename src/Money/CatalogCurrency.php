<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\Fiscal;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use Closure;

final class CatalogCurrency
{
    /** @var (Closure(): Catalogs)|null */
    private static ?Closure $catalogs = null;

    /**
     * @param (Closure(): Catalogs)|null $catalogs
     */
    public static function resolveCatalogsUsing(?Closure $catalogs): void
    {
        self::$catalogs = $catalogs;
    }

    public static function of(string $code, string $field = 'currency'): Currency
    {
        if (! self::isOfficial($code)) {
            throw EfaturaValidationException::invalidCurrency($field);
        }

        try {
            return Currency::of($code);
        } catch (UnknownCurrencyException) {
            return new Currency($code, 0, $code, Fiscal::AMOUNT_SCALE);
        }
    }

    public static function isOfficial(string $code): bool
    {
        $catalogs = self::$catalogs ?? throw DefinitionException::catalogsUnavailable();

        return $catalogs()->find(Catalog::Currencies, $code) !== null;
    }
}
