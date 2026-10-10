<?php

declare(strict_types=1);

namespace Akira\Efatura\Issuance;

use Akira\Efatura\Enums\ReconciliationOutcome;

final readonly class Reconciliation
{
    public function __construct(
        public string $iud,
        public ReconciliationOutcome $outcome,
        public ?string $xml,
    ) {}

    public function authorized(): bool
    {
        return $this->outcome === ReconciliationOutcome::Authorized;
    }
}
