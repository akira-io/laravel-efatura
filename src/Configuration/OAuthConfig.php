<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use SensitiveParameter;

final readonly class OAuthConfig
{
    public function __construct(
        public ?string $clientId,
        #[SensitiveParameter]
        public ?string $clientSecret,
    ) {}
}
