<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

use Akira\Efatura\Concerns\SlicesIdentifierSegments;

enum EventIdSegment
{
    use SlicesIdentifierSegments;

    case Repository;
    case IssueDateTime;
    case TaxId;

    public function length(): int
    {
        return match ($this) {
            self::Repository    => 1,
            self::IssueDateTime => 12,
            self::TaxId         => 9,
        };
    }
}
