<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use SensitiveParameter;

final readonly class OAuthConfig
{
    use RedactsSensitiveParameters;

    public function __construct(
        public ?string $clientId,
        #[SensitiveParameter]
        public ?string $clientSecret,
    ) {}
}
