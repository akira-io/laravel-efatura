<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ContractType: string
{
    case Lease           = '1';
    case Sublease        = '2';
    case UseAssignment   = '3';
    case EquipmentRental = '4';
}
