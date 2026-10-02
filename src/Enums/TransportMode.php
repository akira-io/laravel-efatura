<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum TransportMode: string
{
    case Unspecified        = '0';
    case Maritime           = '1';
    case Rail               = '2';
    case Road               = '3';
    case Air                = '4';
    case Post               = '5';
    case Multimodal         = '6';
    case FixedInstallations = '7';
    case River              = '8';
}
