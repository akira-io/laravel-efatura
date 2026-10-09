<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use JsonSerializable;
use SensitiveParameter;

final readonly class TransmitterConfig implements JsonSerializable
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
