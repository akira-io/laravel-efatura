<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Closure;
use Throwable;

final readonly class CallbackExchangeRateProvider implements ExchangeRateProvider
{
    /**
     * @param Closure(ExchangeRateRequest): mixed $callback
     */
    public function __construct(private Closure $callback) {}

    public function rate(ExchangeRateRequest $request): ExchangeRateQuote
    {
        try {
            $quote = ($this->callback)($request);
        } catch (Throwable $throwable) {
            throw $throwable instanceof ExchangeRateException
                ? $throwable
                : ExchangeRateException::because(ExchangeRateFailure::ProviderUnavailable, ['source' => 'callback'], $throwable);
        }

        if (! $quote instanceof ExchangeRateQuote) {
            throw ExchangeRateException::because(ExchangeRateFailure::ResponseInvalid, ['source' => 'callback']);
        }

        return $quote->answering($request);
    }
}
