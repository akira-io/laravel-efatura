<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\ExchangeRatesConfig;
use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateSource;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\ConfigurationException;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Container\Container;
use Psr\Clock\ClockInterface;

final readonly class ExchangeRateProviderResolver
{
    public function __construct(
        private Container $container,
        private EfaturaConfig $config,
        private CacheFactory $caches,
        private ClockInterface $clock,
        private ExchangeRateClient $client,
    ) {}

    public function provider(): ExchangeRateProvider
    {
        $rates = $this->config->exchangeRates;

        return match ($rates->provider) {
            ExchangeRateSource::Bcv       => $this->bcv($rates),
            ExchangeRateSource::WorldBank => $this->worldBank($rates),
            ExchangeRateSource::Fixed     => new FixedExchangeRateProvider($this->clock, $rates->fixedRates, $rates->fixedName, $rates->fixedRateType),
            ExchangeRateSource::Callback  => new CallbackExchangeRateProvider($this->callback($rates->callback)),
        };
    }

    private function bcv(ExchangeRatesConfig $rates): ExchangeRateProvider
    {
        $provider = new BcvExchangeRateProvider(
            $this->client,
            new BcvExchangeRateParser,
            $this->clock,
            $this->config->http->bcv,
            $rates->bcvRateType,
            $rates->bcvMaxPublicationAgeDays,
        );

        return $this->cached($provider, ExchangeRateSource::Bcv->value . ':age_' . $rates->bcvMaxPublicationAgeDays, $rates->bcvRateType);
    }

    private function worldBank(ExchangeRatesConfig $rates): ExchangeRateProvider
    {
        $provider = new WorldBankExchangeRateProvider(
            $this->client,
            $this->clock,
            $this->config->http->worldBank,
            $rates->worldBankEconomies,
            $rates->worldBankObservationYear,
        );

        $variant = ExchangeRateSource::WorldBank->value . ':' . $rates->worldBankObservationYear->value;

        return $this->cached($provider, $variant, ExchangeRateType::Reference, true);
    }

    private function cached(
        ExchangeRateProvider $provider,
        string $variant,
        ExchangeRateType $defaultType,
        bool $keepsEarlierDays = false,
    ): ExchangeRateProvider {
        return new CachedExchangeRateProvider(
            $provider,
            $this->caches->store($this->config->cache->store),
            $this->config->cache->prefix . ':exchange_rates:' . $variant,
            $this->config->cache->exchangeRatesTtlSeconds,
            $defaultType,
            $keepsEarlierDays,
        );
    }

    /**
     * @param class-string|null $class
     */
    private function callback(?string $class): Closure
    {
        $callback = $class === null ? null : $this->container->make($class);

        if (! \is_callable($callback)) {
            throw new ConfigurationException('configuration.invalid_type', 'efatura.exchange_rates.callback');
        }

        return $callback(...);
    }
}
