<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum RentType: string
{
    case Rent    = '1';
    case Deposit = '2';
    case Advance = '3';
}
