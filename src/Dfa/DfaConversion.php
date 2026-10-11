<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

final readonly class DfaConversion
{
    public function __construct(
        public string $currency,
        public string $amount,
        public string $rate,
    ) {}
}
