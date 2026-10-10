<?php

declare(strict_types=1);

namespace Akira\Efatura\Concerns;

use Akira\Efatura\Support\Fiscal;
use Illuminate\Support\Str;

trait SlicesIdentifierSegments
{
    abstract public function length(): int;

    public function offset(): int
    {
        return \strlen(Fiscal::COUNTRY) + collect(self::cases())->takeUntil($this)->sum(static fn (self $segment): int => $segment->length());
    }

    public function of(string $identifier): string
    {
        return substr($identifier, $this->offset(), $this->length());
    }

    public function padded(int $value): string
    {
        return Str::padLeft((string) $value, $this->length(), '0');
    }
}
