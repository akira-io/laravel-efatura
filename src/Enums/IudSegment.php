<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

use Akira\Efatura\Concerns\SlicesIdentifierSegments;

enum IudSegment
{
    use SlicesIdentifierSegments;

    case Repository;
    case IssueDate;
    case EmitterTaxId;
    case LedCode;
    case DocumentType;
    case DocumentNumber;
    case RandomCode;

    public function length(): int
    {
        return match ($this) {
            self::Repository                         => 1,
            self::IssueDate                          => 6,
            self::EmitterTaxId, self::DocumentNumber => 9,
            self::LedCode                            => 5,
            self::DocumentType                       => 2,
            self::RandomCode                         => 10,
        };
    }
}
