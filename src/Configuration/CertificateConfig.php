<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use Akira\Efatura\Concerns\RefusesSerialization;
use Akira\Efatura\Support\Secret;
use JsonSerializable;
use SensitiveParameter;

final readonly class CertificateConfig implements JsonSerializable
{
    use RedactsSensitiveParameters;
    use RefusesSerialization;

    private ?Secret $passphrase;

    public function __construct(
        public string $disk,
        public ?string $certificatePath,
        public ?string $privateKeyPath,
        #[SensitiveParameter]
        ?string $passphrase,
        public ?string $caBundlePath = null,
    ) {
        $this->passphrase = Secret::from($passphrase);
    }

    public function passphrase(): ?string
    {
        return $this->passphrase?->reveal();
    }
}
