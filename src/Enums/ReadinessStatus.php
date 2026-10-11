<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum ReadinessStatus: string
{
    case Passed  = 'passed';
    case Warning = 'warning';
    case Failed  = 'failed';
    case Skipped = 'skipped';
}
