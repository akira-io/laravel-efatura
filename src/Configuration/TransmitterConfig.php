<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use SensitiveParameter;

final readonly class TransmitterConfig
{
    use RedactsSensitiveParameters;

    public function __construct(
        public ?string $taxId,
        public ?string $name,
        #[SensitiveParameter]
        public ?string $middlewareKey,
        public OAuthConfig $oauth,
    ) {}
}
