<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

enum Environment: int
{
    case Production   = 1;
    case Homologation = 2;
    case Test         = 3;

    public static function fromName(string $name): ?self
    {
        return Arr::first(
            self::cases(),
            static fn (self $case): bool => Str::lower($case->name) === Str::lower($name),
        );
    }

    public function code(): int
    {
        return $this->value;
    }
}
