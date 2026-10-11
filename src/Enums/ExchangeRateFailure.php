<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ExchangeRateFailure: string
{
    case ProviderUnavailable  = 'exchange_rate.provider_unavailable';
    case ResponseInvalid      = 'exchange_rate.response_invalid';
    case CurrencyUnsupported  = 'exchange_rate.currency_unsupported';
    case PairMismatch         = 'exchange_rate.pair_mismatch';
    case RateInvalid          = 'exchange_rate.rate_invalid';
    case DateUnavailable      = 'exchange_rate.date_unavailable';
    case DateInvalid          = 'exchange_rate.date_invalid';
    case Stale                = 'exchange_rate.stale';
    case SourceRequired       = 'exchange_rate.source_required';
    case InvoiceInvalid       = 'exchange_rate.invoice_invalid';
    case AlternativesConflict = 'exchange_rate.alternatives_conflict';

    public function retryable(): bool
    {
        return $this === self::ProviderUnavailable;
    }
}
