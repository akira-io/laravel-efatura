<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum TaxType: string
{
    case NotApplicable = 'NA';
    case ValueAddedTax = 'IVA';
    case StampTax      = 'IS';
    case IncomeTax     = 'IR';
}
