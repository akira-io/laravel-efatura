<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum SignatureFailure: string
{
    case Missing              = 'missing';
    case Multiple             = 'multiple';
    case Placement            = 'placement';
    case AlgorithmUnsupported = 'algorithm_unsupported';
    case ReferenceUnexpected  = 'reference_unexpected';
    case DigestMismatch       = 'digest_mismatch';
    case CertificateInvalid   = 'certificate_invalid';
    case KeyUnsupported       = 'key_unsupported';
    case CertificateMismatch  = 'certificate_mismatch';
    case ValueInvalid         = 'value_invalid';

    public function code(): string
    {
        return 'signature.' . $this->value;
    }
}
