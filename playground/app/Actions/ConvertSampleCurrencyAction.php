<?php

declare(strict_types=1);

namespace App\Actions;

use Akira\Efatura\Actions\ConvertDocumentCurrencyAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Data\DocumentData;
use App\Data\CurrencyConversion;

final readonly class ConvertSampleCurrencyAction
{
    public const string SOURCE_CURRENCY = 'EUR';

    public function __construct(private ConvertDocumentCurrencyAction $convert, private EfaturaConfig $config) {}

    public function handle(DocumentData $document): CurrencyConversion
    {
        $converted = $this->convert->handle($document, self::SOURCE_CURRENCY);

        return new CurrencyConversion(
            self::SOURCE_CURRENCY,
            $document,
            $converted,
            $converted instanceof HasTotals ? $converted->totals->payableAlternativeAmounts : [],
            $this->config->exchangeRates->provider->value,
        );
    }
}
