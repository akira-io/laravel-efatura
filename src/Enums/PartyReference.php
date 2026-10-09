<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum PartyReference: string
{
    case Emitter  = 'EP';
    case Receiver = 'RP';
}
