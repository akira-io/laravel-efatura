<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class SoftwareConfig
{
    public function __construct(
        public ?string $code,
        public ?string $name,
        public ?string $version,
    ) {}
}
