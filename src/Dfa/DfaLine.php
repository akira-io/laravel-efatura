<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

final readonly class DfaLine
{
    public function __construct(
        public string $code,
        public string $description,
        public string $quantity,
        public string $unit,
        public string $unitPrice,
        public string $tax,
        public string $total,
    ) {}
}
