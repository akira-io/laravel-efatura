<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Configuration\HttpClientConfig;
use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Psr\Clock\ClockInterface;

final readonly class BcvExchangeRateProvider implements ExchangeRateProvider
{
    public const string DEFAULT_SOURCE_URL = 'https://www.bcv.cv/pt/PoliticaMonetaria/EstatisticasCambiais/Paginas/Estatisticas_Cambiais.aspx?_expType=PDF';

    public const string NAME = 'Banco de Cabo Verde';

    public const int MAX_BODY_BYTES = 262_144;

    private const string HOST = 'www.bcv.cv';

    private string $sourceUrl;

    public function __construct(
        private ExchangeRateClient $client,
        private BcvExchangeRateParser $parser,
        private ClockInterface $clock,
        private HttpClientConfig $profile,
        private ExchangeRateType $rateType = ExchangeRateType::Buy,
        private int $maxPublicationAgeDays = 0,
    ) {
        $this->sourceUrl = $profile->baseUrl ?? self::DEFAULT_SOURCE_URL;

        if (SourceUrl::parts($this->sourceUrl) !== ['host' => self::HOST, 'port' => null]) {
            throw ExchangeRateException::because(ExchangeRateFailure::SourceRequired);
        }

        if (! self::publishes($rateType)) {
            throw ExchangeRateException::because(ExchangeRateFailure::PairMismatch);
        }

        if ($maxPublicationAgeDays < 0) {
            throw ExchangeRateException::because(ExchangeRateFailure::ResponseInvalid);
        }
    }

    public function rate(ExchangeRateRequest $request): ExchangeRateQuote
    {
        $type = $request->rateType ?? $this->rateType;

        if ($request->targetCurrency !== Fiscal::CURRENCY || ! self::publishes($type)) {
            throw ExchangeRateException::because(ExchangeRateFailure::PairMismatch);
        }

        $html = $this->client->get($this->profile, $this->sourceUrl, 'text/html', 'bcv', self::MAX_BODY_BYTES);
        $rate = $this->parser->parse($html, $request->sourceCurrency, $type);
        $this->checkPublication($rate->publishedOn, Fiscal::local($request->effectiveAt)->startOfDay());

        return new ExchangeRateQuote(
            $request->sourceCurrency,
            Fiscal::CURRENCY,
            $rate->rate,
            $type,
            $rate->publishedOn,
            Fiscal::local(CarbonImmutable::instance($this->clock->now())),
            self::NAME,
            $this->sourceUrl,
        )->answering($request);
    }

    private static function publishes(ExchangeRateType $type): bool
    {
        return \in_array($type, [ExchangeRateType::Buy, ExchangeRateType::Sell], true);
    }

    private function checkPublication(CarbonImmutable $publishedOn, CarbonImmutable $requestedDay): void
    {
        if ($publishedOn->isAfter($requestedDay)) {
            throw ExchangeRateException::because(ExchangeRateFailure::DateInvalid);
        }

        if ($publishedOn->isBefore($requestedDay) && $this->maxPublicationAgeDays === 0) {
            $today = Fiscal::local(CarbonImmutable::instance($this->clock->now()))->startOfDay();

            throw ExchangeRateException::because(ExchangeRateFailure::DateUnavailable, ['source' => 'bcv'], retryable: $requestedDay->equalTo($today));
        }

        if ($publishedOn->addDays($this->maxPublicationAgeDays)->isBefore($requestedDay)) {
            throw ExchangeRateException::because(ExchangeRateFailure::Stale);
        }
    }
}
