<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum EmissionMode: int
{
    case Online  = 1;
    case Offline = 2;
    case Off     = 3;
}
