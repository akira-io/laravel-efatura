<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Spatie\LaravelData\Data;

final class LineItemData extends Data
{
    /**
     * @param array<int, TaxData> $taxes
     */
    public function __construct(
        public readonly string $description,
        public readonly float $quantity,
        public readonly float $unitPrice,
        public readonly float $total,
        public readonly array $taxes = [],
    ) {}
}
