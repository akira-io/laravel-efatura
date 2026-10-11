<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;

final readonly class ExchangeRateRequest
{
    public string $sourceCurrency;

    public string $targetCurrency;

    public function __construct(
        string $sourceCurrency,
        public CarbonImmutable $effectiveAt,
        public ?ExchangeRateType $rateType = null,
        string $targetCurrency = Fiscal::CURRENCY,
    ) {
        $this->sourceCurrency = CurrencyCode::normalize($sourceCurrency);
        $this->targetCurrency = CurrencyCode::normalize($targetCurrency);
    }
}
