<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateSource;
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
            ExchangeRateSource::Bcv => $this->cached(ExchangeRateSource::Bcv, new BcvExchangeRateProvider(
                $this->client,
                new BcvExchangeRateParser,
                $this->clock,
                $this->config->http->bcv,
                $rates->bcvRateType,
                $rates->bcvMaxPublicationAgeDays,
            )),
            ExchangeRateSource::WorldBank => $this->cached(
                ExchangeRateSource::WorldBank,
                new WorldBankExchangeRateProvider($this->client, $this->clock, $this->config->http->worldBank, $rates->worldBankEconomies, $rates->worldBankObservationYear),
            ),
            ExchangeRateSource::Fixed    => new FixedExchangeRateProvider($this->clock, $rates->fixedRates, $rates->fixedName, $rates->fixedRateType),
            ExchangeRateSource::Callback => new CallbackExchangeRateProvider($this->callback($rates->callback)),
        };
    }

    private function cached(ExchangeRateSource $source, ExchangeRateProvider $provider): ExchangeRateProvider
    {
        return new CachedExchangeRateProvider(
            $provider,
            $this->caches->store($this->config->cache->store),
            $this->config->cache->prefix . ':exchange_rates:' . $source->value,
            $this->config->cache->exchangeRatesTtlSeconds,
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
