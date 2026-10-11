<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Psr\Clock\ClockInterface;

final readonly class FixedExchangeRateProvider implements ExchangeRateProvider
{
    /** @var array<string, BigDecimal> */
    private array $rates;

    /**
     * @param array<string, BigDecimal|int|string> $rates
     */
    public function __construct(
        private ClockInterface $clock,
        array $rates,
        private string $name = 'Fixed rate',
        private ExchangeRateType $rateType = ExchangeRateType::Custom,
        private ?string $sourceUrl = null,
    ) {
        $now         = CarbonImmutable::instance($clock->now());
        $this->rates = collect($rates)
            ->mapWithKeys(fn (BigDecimal|int|string $rate, string $currency): array => [
                CurrencyCode::normalize($currency) => $this->quote($currency, $rate, $now, $now)->rate,
            ])
            ->all();
    }

    public function rate(ExchangeRateRequest $request): ExchangeRateQuote
    {
        $rate = $this->rates[$request->sourceCurrency] ?? throw ExchangeRateException::because(ExchangeRateFailure::CurrencyUnsupported);

        return $this->quote($request->sourceCurrency, $rate, $request->effectiveAt, Fiscal::local(CarbonImmutable::instance($this->clock->now())))
            ->answering($request);
    }

    private function quote(string $currency, BigDecimal|int|string $rate, CarbonImmutable $effectiveAt, CarbonImmutable $retrievedAt): ExchangeRateQuote
    {
        return new ExchangeRateQuote($currency, Fiscal::CURRENCY, $rate, $this->rateType, $effectiveAt, $retrievedAt, $this->name, $this->sourceUrl);
    }
}
