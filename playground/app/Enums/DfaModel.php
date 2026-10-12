<?php

declare(strict_types=1);

namespace App\Enums;

use Akira\Efatura\Enums\EmissionMode;

enum DfaModel: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Off = 'off';

    public function emissionMode(): EmissionMode
    {
        return match ($this) {
            self::Online => EmissionMode::Online,
            self::Offline => EmissionMode::Offline,
            self::Off => EmissionMode::Off,
        };
    }
}
