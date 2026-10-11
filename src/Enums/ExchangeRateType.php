<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ExchangeRateType: string
{
    case Buy       = 'buy';
    case Sell      = 'sell';
    case Reference = 'reference';
    case Custom    = 'custom';
}
