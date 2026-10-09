<?php

declare(strict_types=1);

namespace Akira\Efatura\Configuration;

use Akira\Efatura\Concerns\RedactsSensitiveParameters;
use SensitiveParameter;

final readonly class CertificateConfig
{
    use RedactsSensitiveParameters;

    public function __construct(
        public string $disk,
        public ?string $certificatePath,
        public ?string $privateKeyPath,
        #[SensitiveParameter]
        public ?string $passphrase,
    ) {}
}
