<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum PackageKind: string
{
    case Documents = 'dfe';
    case Events    = 'event';
}
