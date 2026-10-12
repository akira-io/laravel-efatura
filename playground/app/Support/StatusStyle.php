<?php

declare(strict_types=1);

namespace App\Support;

use Akira\Efatura\Enums\ReadinessStatus;

final class StatusStyle
{
    public static function of(ReadinessStatus $status): string
    {
        return match ($status) {
            ReadinessStatus::Passed => 'ok',
            ReadinessStatus::Failed => 'bad',
            ReadinessStatus::Warning => 'warning',
            ReadinessStatus::Skipped => 'muted',
        };
    }
}
