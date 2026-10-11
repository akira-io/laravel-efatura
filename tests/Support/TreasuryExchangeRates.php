<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\ExchangeRateRequest;
use Akira\Efatura\Enums\ExchangeRateType;

final class TreasuryExchangeRates
{
    public function __invoke(ExchangeRateRequest $request): ExchangeRateQuote
    {
        return new ExchangeRateQuote($request->sourceCurrency, $request->targetCurrency, '110.265', ExchangeRateType::Custom, $request->effectiveAt, $request->effectiveAt, 'Treasury');
    }
}
