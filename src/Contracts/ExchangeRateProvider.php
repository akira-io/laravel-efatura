<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\ExchangeRateRequest;

interface ExchangeRateProvider
{
    public function rate(ExchangeRateRequest $request): ExchangeRateQuote;
}
