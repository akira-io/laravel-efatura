<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum RentPurpose: string
{
    case Commercial  = '1';
    case Residential = '2';
    case Industrial  = '3';
}
