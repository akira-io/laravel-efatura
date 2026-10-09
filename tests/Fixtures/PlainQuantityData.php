<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Fixtures;

use Spatie\LaravelData\Data;

final class PlainQuantityData extends Data
{
    public function __construct(
        public readonly string $value,
        public readonly string $unitCode,
    ) {}
}
