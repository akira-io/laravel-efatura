<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum TransportDocumentType: string
{
    case Dispatch       = '1';
    case Transport      = '2';
    case OwnAssets      = '3';
    case Consignment    = '4';
    case CustomerReturn = '5';
}
