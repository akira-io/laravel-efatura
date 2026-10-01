<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

use Illuminate\Support\Arr;

enum Environment: int
{
    case PRODUCTION   = 1;
    case HOMOLOGATION = 2;
    case TEST         = 3;

    public static function fromName(string $name): ?self
    {
        return Arr::first(
            self::cases(),
            static fn (self $case): bool => $case->name === $name,
        );
    }

    public function code(): int
    {
        return $this->value;
    }
}
