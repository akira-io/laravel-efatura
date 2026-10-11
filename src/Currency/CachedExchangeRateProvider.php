<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Throwable;

final readonly class CachedExchangeRateProvider implements ExchangeRateProvider
{
    public function __construct(
        private ExchangeRateProvider $provider,
        private Repository $cache,
        private string $prefix,
        private int $ttlSeconds,
        private ExchangeRateType $defaultType,
        private bool $keepsEarlierDays = false,
    ) {}

    public function rate(ExchangeRateRequest $request): ExchangeRateQuote
    {
        $key    = $this->key($request);
        $cached = $this->restored($this->read($key));

        if ($cached instanceof ExchangeRateQuote) {
            return $cached->answering($request);
        }

        $quote = $this->provider->rate($request);

        if ($this->keepsEarlierDays || ! Fiscal::local($quote->effectiveAt)->startOfDay()->isBefore(Fiscal::local($request->effectiveAt)->startOfDay())) {
            $this->write($key, $quote);
        }

        return $quote;
    }

    private function key(ExchangeRateRequest $request): string
    {
        return implode(':', [
            $this->prefix,
            $request->sourceCurrency,
            $request->targetCurrency,
            ($request->rateType ?? $this->defaultType)->value,
            Fiscal::local($request->effectiveAt)->toDateString(),
        ]);
    }

    private function read(string $key): mixed
    {
        try {
            return $this->cache->get($key);
        } catch (Throwable) {
            return null;
        }
    }

    private function write(string $key, ExchangeRateQuote $quote): void
    {
        try {
            $this->cache->put($key, [
                'sourceCurrency' => $quote->sourceCurrency,
                'targetCurrency' => $quote->targetCurrency,
                'rate'           => (string) $quote->rate,
                'rateType'       => $quote->rateType->value,
                'effectiveAt'    => $quote->effectiveAt->toIso8601String(),
                'retrievedAt'    => $quote->retrievedAt->toIso8601String(),
                'provider'       => $quote->provider,
                'sourceUrl'      => $quote->sourceUrl,
                'evidence'       => $quote->evidence,
            ], $this->ttlSeconds);
        } catch (Throwable) {
            return;
        }
    }

    private function restored(mixed $entry): ?ExchangeRateQuote
    {
        if (! \is_array($entry)) {
            return null;
        }

        try {
            return new ExchangeRateQuote(
                Arr::string($entry, 'sourceCurrency'),
                Arr::string($entry, 'targetCurrency'),
                Arr::string($entry, 'rate'),
                ExchangeRateType::from(Arr::string($entry, 'rateType')),
                Fiscal::local(CarbonImmutable::parse(Arr::string($entry, 'effectiveAt'))),
                Fiscal::local(CarbonImmutable::parse(Arr::string($entry, 'retrievedAt'))),
                Arr::string($entry, 'provider'),
                isset($entry['sourceUrl']) ? Arr::string($entry, 'sourceUrl') : null,
                Arr::array($entry, 'evidence'),
            );
        } catch (Throwable) {
            return null;
        }
    }
}
