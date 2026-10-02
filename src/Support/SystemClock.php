<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Contracts\Clock;
use Carbon\CarbonImmutable;

final readonly class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('Atlantic/Cape_Verde');
    }
}
