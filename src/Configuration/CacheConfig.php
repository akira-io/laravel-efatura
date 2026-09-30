<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class CacheConfig
{
    public function __construct(
        public string $store,
        public string $prefix,
        public int $exchangeRatesTtlSeconds,
    ) {}
}
