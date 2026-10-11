<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

final readonly class DfaParty
{
    public function __construct(
        public ?string $taxId,
        public ?string $name,
        public ?string $address,
        public ?string $contact,
    ) {}
}
