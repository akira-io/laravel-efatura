<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class ExchangeRateQuote
{
    public string $sourceCurrency;

    public string $targetCurrency;

    public BigDecimal $rate;

    public string $provider;

    public ?string $sourceUrl;

    /** @var array<string, string> */
    public array $evidence;

    /**
     * @param array<array-key, mixed> $evidence
     */
    public function __construct(
        string $sourceCurrency,
        string $targetCurrency,
        BigDecimal|int|string $rate,
        public ExchangeRateType $rateType,
        public CarbonImmutable $effectiveAt,
        public CarbonImmutable $retrievedAt,
        string $provider,
        ?string $sourceUrl = null,
        array $evidence = [],
    ) {
        $this->sourceCurrency = CurrencyCode::normalize($sourceCurrency);
        $this->targetCurrency = CurrencyCode::normalize($targetCurrency);
        $this->rate           = self::rate($rate);
        $this->provider       = Str::trim($provider) !== '' ? Str::trim($provider) : throw ExchangeRateException::because(ExchangeRateFailure::ResponseInvalid);
        $this->sourceUrl      = $sourceUrl === null ? null : SourceUrl::validated($sourceUrl);
        $this->evidence       = self::evidence($evidence);
    }

    public static function identity(CarbonImmutable $effectiveAt, CarbonImmutable $retrievedAt): self
    {
        return new self(Fiscal::CURRENCY, Fiscal::CURRENCY, 1, ExchangeRateType::Reference, $effectiveAt, $retrievedAt, 'identity');
    }

    public function answering(ExchangeRateRequest $request): self
    {
        if ($this->sourceCurrency !== $request->sourceCurrency || $this->targetCurrency !== $request->targetCurrency
            || ($request->rateType instanceof ExchangeRateType && $request->rateType !== $this->rateType)) {
            throw ExchangeRateException::because(ExchangeRateFailure::PairMismatch);
        }

        if ($this->effectiveAt->isAfter($request->effectiveAt)) {
            throw ExchangeRateException::because(ExchangeRateFailure::DateUnavailable);
        }

        return $this;
    }

    private static function rate(BigDecimal|int|string $rate): BigDecimal
    {
        try {
            $scaled = BigDecimal::of($rate)->toScale(Fiscal::AMOUNT_SCALE, DecimalFormatter::fiscalRounding());
        } catch (MathException) {
            throw ExchangeRateException::because(ExchangeRateFailure::RateInvalid);
        }

        if (! $scaled->isPositive() || ! DecimalFormatter::fitsIntegerDigits($scaled)) {
            throw ExchangeRateException::because(ExchangeRateFailure::RateInvalid);
        }

        return $scaled;
    }

    /**
     * @param  array<array-key, mixed> $evidence
     * @return array<string, string>
     */
    private static function evidence(array $evidence): array
    {
        $validated = [];
        foreach ($evidence as $key => $value) {
            if (! \is_string($key) || ! \is_string($value) || Str::trim($key) === '' || Str::trim($value) === '') {
                throw ExchangeRateException::because(ExchangeRateFailure::ResponseInvalid);
            }

            $validated[$key] = Str::endsWith($key, '_url') ? SourceUrl::validated($value) : $value;
        }

        return $validated;
    }
}
