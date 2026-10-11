<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Currency\CurrencyCode;
use Akira\Efatura\Currency\CveConversion;
use Akira\Efatura\Currency\DeferredExchangeRateProvider;
use Akira\Efatura\Currency\ExchangeRateQuote;
use Akira\Efatura\Currency\ExchangeRateRequest;
use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Support\Fiscal;
use Illuminate\Validation\ValidationException;

final readonly class ConvertDocumentCurrencyAction
{
    public function __construct(private DeferredExchangeRateProvider $provider) {}

    /**
     * @template T of DocumentData
     *
     * @param  T $document
     * @return T
     */
    public function handle(DocumentData $document, string $sourceCurrency, ?ExchangeRateQuote $quote = null): DocumentData
    {
        $currency = CurrencyCode::normalize($sourceCurrency);

        if (! $document instanceof HasTotals) {
            throw ExchangeRateException::because(ExchangeRateFailure::InvoiceInvalid);
        }

        if ($currency === Fiscal::CURRENCY) {
            return $this->validated($document, $document->toPayload());
        }

        if ($document->totals->payableAlternativeAmounts !== []) {
            throw ExchangeRateException::because(ExchangeRateFailure::AlternativesConflict);
        }

        $request = new ExchangeRateRequest($currency, Fiscal::local($document->header->issueDate)->setTimeFrom(Fiscal::local($document->header->issueTime)));
        $quote   = ($quote ?? $this->provider->rate($request))->answering($request);

        return $this->validated($document, new CveConversion($quote)->payload($document->toPayload(), $document));
    }

    /**
     * @template T of DocumentData
     *
     * @param  T                       $document
     * @param  array<array-key, mixed> $payload
     * @return T
     */
    private function validated(DocumentData $document, array $payload): DocumentData
    {
        try {
            return $document::from($payload);
        } catch (ValidationException $validationException) {
            throw ExchangeRateException::because(ExchangeRateFailure::InvoiceInvalid, previous: $validationException);
        }
    }
}
