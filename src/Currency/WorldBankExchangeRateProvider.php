<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Configuration\HttpClientConfig;
use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;

final readonly class WorldBankExchangeRateProvider implements ExchangeRateProvider
{
    public const string INDICATOR = 'PA.NUS.FCRF';

    private const string HOST = 'api.worldbank.org';

    private const string INDICATOR_URL = 'https://data.worldbank.org/indicator/' . self::INDICATOR;

    private const string ANCHOR = 'USD';

    private const string CAPE_VERDE_ECONOMY = 'CPV';

    /** @var array<string, string> */
    private array $economies;

    /**
     * @param array<string, string> $economies
     */
    public function __construct(private ExchangeRateClient $client, private ClockInterface $clock, private HttpClientConfig $profile, array $economies = [])
    {
        if ($profile->baseUrl !== null && SourceUrl::parts($profile->baseUrl) !== ['host' => self::HOST, 'port' => null]) {
            throw ExchangeRateException::because(ExchangeRateFailure::SourceRequired);
        }

        $this->economies = [...self::economies($economies), Fiscal::CURRENCY => self::CAPE_VERDE_ECONOMY];
    }

    public function rate(ExchangeRateRequest $request): ExchangeRateQuote
    {
        if (($request->rateType ?? ExchangeRateType::Reference) !== ExchangeRateType::Reference) {
            throw ExchangeRateException::because(ExchangeRateFailure::PairMismatch);
        }

        foreach ([$request->sourceCurrency, $request->targetCurrency] as $currency) {
            if ($currency !== self::ANCHOR && ! isset($this->economies[$currency])) {
                throw ExchangeRateException::because(ExchangeRateFailure::CurrencyUnsupported);
            }
        }

        $year     = Fiscal::local($request->effectiveAt)->year;
        $source   = $this->observation('source', $request->sourceCurrency, $year);
        $target   = $this->observation('target', $request->targetCurrency, $year);
        $evidence = ['source' => 'World Bank', 'indicator' => self::INDICATOR, 'observation_period' => (string) $year, ...$source, ...$target];

        return new ExchangeRateQuote(
            $request->sourceCurrency,
            $request->targetCurrency,
            BigDecimal::of($target['target_value'])->dividedBy($source['source_value'], Fiscal::AMOUNT_SCALE, DecimalFormatter::fiscalRounding()),
            ExchangeRateType::Reference,
            new CarbonImmutable($year . '-01-01', Fiscal::TIMEZONE),
            Fiscal::local(CarbonImmutable::instance($this->clock->now())),
            'World Bank ' . self::INDICATOR,
            self::INDICATOR_URL,
            $evidence,
        )->answering($request);
    }

    /**
     * @param  array<string, string> $economies
     * @return array<string, string>
     */
    private static function economies(array $economies): array
    {
        $normalized = [];
        foreach ($economies as $currency => $economy) {
            $code = Str::upper(Str::trim($economy));
            if (preg_match('/\A[A-Z0-9]{2,3}\z/', $code) !== 1) {
                throw ExchangeRateException::because(ExchangeRateFailure::ResponseInvalid);
            }

            $normalized[CurrencyCode::normalize($currency)] = $code;
        }

        if (($normalized[Fiscal::CURRENCY] ?? self::CAPE_VERDE_ECONOMY) !== self::CAPE_VERDE_ECONOMY) {
            throw ExchangeRateException::because(ExchangeRateFailure::ResponseInvalid);
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function observation(string $role, string $currency, int $year): array
    {
        if ($currency === self::ANCHOR) {
            return [$role . '_currency' => $currency, $role . '_value' => '1'];
        }

        $economy = $this->economies[$currency];
        $url     = 'https://' . self::HOST . '/v2/country/' . $economy . '/indicator/' . self::INDICATOR . '?format=json&date=' . $year . '&per_page=1';
        $value   = WorldBankObservation::value($this->client->get($this->profile, $url, 'application/json', 'world_bank'), $economy, $year);

        return [$role . '_currency' => $currency, $role . '_economy' => $economy, $role . '_value' => (string) $value, $role . '_url' => $url];
    }
}
