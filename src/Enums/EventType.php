<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum EventType: string
{
    case FiscalDocumentCancellation = 'FDC';
    case UnusedDocumentNumber       = 'UDN';
}
