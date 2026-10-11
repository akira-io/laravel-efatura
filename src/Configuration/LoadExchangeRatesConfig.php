<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\ReadsConfigurationValues;
use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Enums\ExchangeRateSource;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Enums\WorldBankObservationYear;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Money\CatalogCurrency;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use SensitiveParameter;

final readonly class LoadExchangeRatesConfig
{
    use ReadsConfigurationValues;

    private const string PATH = 'efatura.exchange_rates';

    private const string CAPE_VERDE_ECONOMY = 'CPV';

    public function __construct(#[SensitiveParameter] private Repository $repository) {}

    public function __invoke(): ExchangeRatesConfig
    {
        foreach (['', '.bcv', '.world_bank', '.fixed'] as $section) {
            $this->section(self::PATH . $section);
        }

        $defaults = new ExchangeRatesConfig;
        $provider = $this->provider($defaults->provider);

        return new ExchangeRatesConfig(
            $provider,
            $this->rateType('.bcv.rate_type', $defaults->bcvRateType, [ExchangeRateType::Buy, ExchangeRateType::Sell]),
            $this->integer(self::PATH . '.bcv.max_publication_age_days', $defaults->bcvMaxPublicationAgeDays, 0),
            $this->economies(),
            $this->codeMap('.fixed.rates', null),
            $this->rateType('.fixed.rate_type', $defaults->fixedRateType, ExchangeRateType::cases()),
            $this->requiredString(self::PATH . '.fixed.name', $defaults->fixedName),
            $this->callback($provider),
            $this->observationYear($defaults->worldBankObservationYear),
        );
    }

    private function repository(): Repository
    {
        return $this->repository;
    }

    private function provider(ExchangeRateSource $default): ExchangeRateSource
    {
        $value = $this->repository->get(self::PATH . '.provider') ?? $default;
        if ($value instanceof ExchangeRateSource) {
            return $value;
        }

        $provider = \is_string($value) ? ExchangeRateSource::tryFrom(Str::lower(Str::trim($value))) : null;

        return $provider ?? throw new ConfigurationException('configuration.invalid_exchange_rate_provider', self::PATH . '.provider');
    }

    private function observationYear(WorldBankObservationYear $default): WorldBankObservationYear
    {
        $path  = self::PATH . '.world_bank.observation_year';
        $value = $this->repository->get($path) ?? $default;
        $year  = \is_string($value) ? WorldBankObservationYear::tryFrom(Str::lower(Str::trim($value))) : $value;

        return $year instanceof WorldBankObservationYear ? $year : throw new ConfigurationException('configuration.invalid_observation_year', $path);
    }

    /**
     * @param list<ExchangeRateType> $allowed
     */
    private function rateType(string $path, ExchangeRateType $default, array $allowed): ExchangeRateType
    {
        $value = $this->repository->get(self::PATH . $path) ?? $default;
        $type  = \is_string($value) ? ExchangeRateType::tryFrom(Str::lower(Str::trim($value))) : $value;

        if (! \in_array($type, $allowed, true)) {
            throw new ConfigurationException('configuration.invalid_rate_type', self::PATH . $path);
        }

        return $type;
    }

    /**
     * @return array<string, string>
     */
    private function codeMap(string $path, ?string $valuePattern): array
    {
        $map = $this->repository->get(self::PATH . $path) ?? [];
        if (! \is_array($map)) {
            throw new ConfigurationException('configuration.invalid_type', self::PATH . $path);
        }

        $normalized = [];
        foreach ($map as $code => $value) {
            $currency = \is_string($code) ? Str::upper(Str::trim($code)) : '';
            $value    = \is_int($value) ? (string) $value : $value;
            $value    = \is_string($value) && $valuePattern !== null ? Str::upper(Str::trim($value)) : $value;

            if (preg_match('/\A[A-Z]{3}\z/', $currency) !== 1 || ! CatalogCurrency::isOfficial($currency)
                || ! \is_string($value) || ! $this->acceptable($value, $valuePattern)) {
                throw new ConfigurationException('configuration.invalid_type', self::PATH . $path);
            }

            $normalized[$currency] = $value;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function economies(): array
    {
        $economies = $this->codeMap('.world_bank.economies', '/\A[A-Z0-9]{2,3}\z/');

        if (($economies[Fiscal::CURRENCY] ?? self::CAPE_VERDE_ECONOMY) !== self::CAPE_VERDE_ECONOMY) {
            throw new ConfigurationException('configuration.invalid_type', self::PATH . '.world_bank.economies');
        }

        return $economies;
    }

    private function acceptable(string $value, ?string $pattern): bool
    {
        if ($pattern !== null) {
            return preg_match($pattern, $value) === 1;
        }

        return Str::length($value) <= ExchangeRateQuote::MAX_RATE_LENGTH
            && DecimalFormatter::isPlainDecimal($value)
            && BigDecimal::of($value)->isPositive();
    }

    /**
     * @return class-string|null
     */
    private function callback(ExchangeRateSource $provider): ?string
    {
        $callback = $this->string(self::PATH . '.callback');

        if (($callback === null && $provider === ExchangeRateSource::Callback) || ($callback !== null && ! class_exists($callback))) {
            throw new ConfigurationException('configuration.invalid_type', self::PATH . '.callback');
        }

        return $callback;
    }
}
