<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum DiscountValueType: string
{
    case Amount     = 'A';
    case Percentage = 'P';
}
