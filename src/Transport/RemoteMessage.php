<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

final readonly class RemoteMessage
{
    public function __construct(
        public string $source,
        public string $type,
        public ?string $dateTime,
        public string $code,
        public ?string $description,
        public ?string $location,
    ) {}
}
