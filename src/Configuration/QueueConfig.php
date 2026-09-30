<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

final readonly class QueueConfig
{
    public function __construct(
        public string $connection,
        public ?string $queue,
    ) {}
}
