<?php

declare(strict_types=1);

namespace App\Data;

final readonly class SampleLine
{
    public function __construct(public string $description, public string $quantity, public string $unitPrice) {}
}
