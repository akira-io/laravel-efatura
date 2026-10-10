<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ReconciliationOutcome: string
{
    case Authorized = 'authorized';
    case NotFound   = 'not_found';
}
