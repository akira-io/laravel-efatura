<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum StampTaxCode: int
{
    case CreditOperations            = 1;
    case FinancialServices           = 2;
    case Guarantees                  = 3;
    case Insurance                   = 4;
    case CreditInstruments           = 5;
    case CorporateOperations         = 6;
    case NotarialAndRegistrationActs = 7;
    case AdministrativeActs          = 8;
    case Contracts                   = 9;
}
