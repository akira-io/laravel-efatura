<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use JsonSerializable;
use SensitiveParameter;

final readonly class CertificateConfig implements JsonSerializable
{
    use RedactsSensitiveParameters;

    public function __construct(
        public string $disk,
        public ?string $certificatePath,
        public ?string $privateKeyPath,
        #[SensitiveParameter]
        public ?string $passphrase,
        public ?string $caBundlePath = null,
    ) {}
}
