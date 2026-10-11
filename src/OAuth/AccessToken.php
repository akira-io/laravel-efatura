<?php

declare(strict_types=1);

namespace Akira\Efatura\OAuth;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use Akira\Efatura\Exceptions\DefinitionException;
use Carbon\CarbonImmutable;
use JsonSerializable;
use SensitiveParameter;

final readonly class AccessToken implements JsonSerializable
{
    use RedactsSensitiveParameters;

    public function __construct(
        #[SensitiveParameter]
        public string $value,
        public CarbonImmutable $expiresAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw DefinitionException::tokenSerialization();
    }
}
