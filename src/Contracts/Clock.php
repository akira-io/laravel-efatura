<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Carbon\CarbonImmutable;

interface Clock
{
    public function now(): CarbonImmutable;
}
