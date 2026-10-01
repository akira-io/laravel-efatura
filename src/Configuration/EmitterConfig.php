<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class EmitterConfig
{
    public function __construct(
        public ?string $taxId,
        public ?string $name,
        public ?string $led,
        public AddressConfig $address,
        public ContactsConfig $contacts,
    ) {}
}
