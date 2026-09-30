<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use SensitiveParameter;

final readonly class TransmitterConfig
{
    public function __construct(
        public ?string $taxId,
        public ?string $name,
        #[SensitiveParameter]
        public ?string $middlewareKey,
        public OAuthConfig $oauth,
    ) {}
}
