<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class HttpClientConfig
{
    public function __construct(
        public ?string $baseUrl,
        public int $timeoutSeconds,
        public int $connectTimeoutSeconds,
        public int $retries,
        public int $retryDelayMilliseconds,
        public int $concurrency,
        public bool $verifyTls,
    ) {}
}
