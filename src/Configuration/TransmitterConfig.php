<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use Akira\Efatura\Concerns\RefusesSerialization;
use Akira\Efatura\Support\Secret;
use JsonSerializable;
use SensitiveParameter;

final readonly class TransmitterConfig implements JsonSerializable
{
    use RedactsSensitiveParameters;
    use RefusesSerialization;

    private ?Secret $middlewareKey;

    public function __construct(
        public ?string $taxId,
        public ?string $name,
        #[SensitiveParameter]
        ?string $middlewareKey,
        public OAuthConfig $oauth,
    ) {
        $this->middlewareKey = Secret::from($middlewareKey);
    }

    public function middlewareKey(): ?string
    {
        return $this->middlewareKey?->reveal();
    }
}
