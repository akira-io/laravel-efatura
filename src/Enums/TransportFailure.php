<?php

declare(strict_types=1);

namespace Akira\Efatura\Enums;

enum TransportFailure: string
{
    case ConnectionFailed   = 'transport.connection_failed';
    case OutcomeUnknown     = 'transport.outcome_unknown';
    case UnexpectedResponse = 'transport.unexpected_response';
    case ResponseTooLarge   = 'transport.response_too_large';
    case Unauthorized       = 'transport.unauthorized';
    case RateLimited        = 'transport.rate_limited';
    case PayloadTooLarge    = 'transport.payload_too_large';
    case HttpError          = 'transport.http_error';
    case RepositoryMismatch = 'transport.repository_mismatch';
    case MissingCredentials = 'transport.missing_credentials';
    case MixedEmitters      = 'transport.mixed_emitters';
    case Rejected           = 'transport.rejected';

    public function retryable(): bool
    {
        return \in_array($this, [self::ConnectionFailed, self::RateLimited], true);
    }
}
