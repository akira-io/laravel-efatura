<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Akira\Efatura\Enums\SignatureProfile;
use Carbon\CarbonImmutable;

final readonly class SignedXml
{
    public function __construct(
        public string $xml,
        public string $id,
        public SignatureProfile $profile,
        public CarbonImmutable $signingTime,
        public string $certificateDigest,
        public string $issuerName,
        public string $serialNumber,
    ) {}
}
