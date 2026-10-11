<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ExchangeRateSource: string
{
    case Bcv       = 'bcv';
    case WorldBank = 'world_bank';
    case Fixed     = 'fixed';
    case Callback  = 'callback';
}
