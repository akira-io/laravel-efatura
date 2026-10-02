<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum LineType: string
{
    case Normal      = 'N';
    case Charge      = 'C';
    case Deduction   = 'D';
    case Information = 'I';

    public function sign(): int
    {
        return match ($this) {
            self::Normal, self::Charge => 1,
            self::Deduction            => -1,
            self::Information          => 0,
        };
    }

    public function participatesInTotals(): bool
    {
        return $this !== self::Information;
    }
}
