<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Enums\ExchangeRateSource;
use Akira\Efatura\Enums\ExchangeRateType;

final readonly class ExchangeRatesConfig
{
    /**
     * @param array<string, string> $worldBankEconomies
     * @param array<string, string> $fixedRates
     * @param class-string|null     $callback
     */
    public function __construct(
        public ExchangeRateSource $provider = ExchangeRateSource::Bcv,
        public ExchangeRateType $bcvRateType = ExchangeRateType::Buy,
        public int $bcvMaxPublicationAgeDays = 0,
        public array $worldBankEconomies = [],
        public array $fixedRates = [],
        public ExchangeRateType $fixedRateType = ExchangeRateType::Custom,
        public string $fixedName = 'Fixed rate',
        public ?string $callback = null,
    ) {}
}
