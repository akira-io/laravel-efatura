<?php

declare(strict_types=1);

namespace App\Data;

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\PayableAlternativeAmountData;

final readonly class CurrencyConversion
{
    /**
     * @param  list<PayableAlternativeAmountData>  $alternatives
     */
    public function __construct(
        public string $sourceCurrency,
        public DocumentData $source,
        public DocumentData $converted,
        public array $alternatives,
        public string $provider,
    ) {}
}
