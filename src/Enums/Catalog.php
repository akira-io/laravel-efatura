<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum Catalog: string
{
    case Units               = 'units';
    case Countries           = 'countries';
    case Locations           = 'locations';
    case Currencies          = 'currencies';
    case PaymentMeans        = 'payment_means';
    case TaxExemptionReasons = 'tax_exemption_reasons';
}
