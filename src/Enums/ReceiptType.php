<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ReceiptType: string
{
    case Commercial           = '1';
    case Service              = '2';
    case CommercialAndService = '3';
    case Rent                 = '4';
}
