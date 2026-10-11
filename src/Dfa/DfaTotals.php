<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

final readonly class DfaTotals
{
    public function __construct(
        public int $itemCount,
        public string $net,
        public string $charges,
        public string $discounts,
        public string $tax,
        public ?string $withholding,
        public ?string $rounding,
        public string $payable,
    ) {}
}
