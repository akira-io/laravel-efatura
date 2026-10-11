<?php

declare(strict_types=1);

namespace Akira\Efatura\Signing;

use Akira\Efatura\Enums\SignatureFailure;
use Akira\Efatura\Enums\SignatureProfile;

final readonly class SignatureVerification
{
    public bool $valid;

    /**
     * @param list<SignatureFailure> $failures
     */
    public function __construct(
        public array $failures,
        public ?SignatureProfile $profile = null,
        public ?string $certificateSubject = null,
        public ?string $certificateSerialNumber = null,
        public ?string $certificateDigest = null,
    ) {
        $this->valid = $failures === [];
    }
}
