<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Enums\LineType;
use Brick\Math\BigDecimal;

final readonly class ConvertedLine
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        public LineType $type,
        public BigDecimal $priceExtension,
        public BigDecimal $net,
        public BigDecimal $discount,
        public BigDecimal $tax,
        public BigDecimal $withholding,
        public bool $withholds,
        public array $payload,
    ) {}
}
