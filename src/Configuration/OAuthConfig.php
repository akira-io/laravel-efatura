<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use Akira\Efatura\Concerns\RefusesSerialization;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\Secret;
use JsonSerializable;
use SensitiveParameter;

final readonly class OAuthConfig implements JsonSerializable
{
    use RedactsSensitiveParameters;
    use RefusesSerialization;

    private ?Secret $clientSecret;

    public function __construct(
        public ?string $clientId,
        #[SensitiveParameter]
        ?string $clientSecret,
        public string $issuerUrl = Fiscal::OAUTH_ISSUER_URL,
    ) {
        $this->clientSecret = Secret::from($clientSecret);
    }

    public function clientSecret(): ?string
    {
        return $this->clientSecret?->reveal();
    }
}
