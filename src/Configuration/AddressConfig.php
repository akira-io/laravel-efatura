<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class AddressConfig
{
    public function __construct(
        public ?string $countryCode,
        public ?string $region,
        public ?string $city,
        public ?string $street,
        public ?string $postalCode,
    ) {}
}
