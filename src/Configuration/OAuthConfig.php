<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use JsonSerializable;
use SensitiveParameter;

final readonly class OAuthConfig implements JsonSerializable
{
    use RedactsSensitiveParameters;

    public function __construct(
        public ?string $clientId,
        #[SensitiveParameter]
        public ?string $clientSecret,
    ) {}
}
