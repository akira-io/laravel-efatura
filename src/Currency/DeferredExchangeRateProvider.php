<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Contracts\ExchangeRateProvider;
use Illuminate\Contracts\Container\Container;

final readonly class DeferredExchangeRateProvider implements ExchangeRateProvider
{
    public function __construct(private Container $container) {}

    public function rate(ExchangeRateRequest $request): ExchangeRateQuote
    {
        return $this->container->make(ExchangeRateProvider::class)->rate($request);
    }
}
