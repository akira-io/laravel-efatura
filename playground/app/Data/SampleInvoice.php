<?php

declare(strict_types=1);

namespace App\Data;

use Akira\Efatura\Enums\EmissionMode;

final readonly class SampleInvoice
{
    /**
     * @param  list<SampleLine>  $lines
     */
    public function __construct(
        public array $lines,
        public string $taxPercentage,
        public ?string $payableOverride = null,
        public EmissionMode $mode = EmissionMode::Online,
    ) {}
}
