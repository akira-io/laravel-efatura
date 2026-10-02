<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class ContactsConfig
{
    public function __construct(
        public ?string $email,
        public ?string $telephone,
        public ?string $mobile,
        public ?string $telefax = null,
        public ?string $website = null,
    ) {}
}
