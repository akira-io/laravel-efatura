<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum TransportDriver: string
{
    case Middleware = 'middleware';
    case Platform   = 'platform';
}
