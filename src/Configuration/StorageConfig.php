<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class StorageConfig
{
    public function __construct(
        public string $disk,
        public string $path,
    ) {}
}
