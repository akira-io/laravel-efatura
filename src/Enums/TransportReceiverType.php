<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum TransportReceiverType: string
{
    case Taxpayer     = '1';
    case NonTaxpayer  = '2';
    case Undetermined = '3';
}
