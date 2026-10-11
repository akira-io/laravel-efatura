<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Enums\TransportFailure;

final readonly class SubmissionStatus
{
    public static function failure(int $status): TransportFailure
    {
        return match ($status) {
            401, 403      => TransportFailure::Unauthorized,
            404, 405, 415 => TransportFailure::HttpError,
            413           => TransportFailure::PayloadTooLarge,
            429           => TransportFailure::RateLimited,
            default       => TransportFailure::OutcomeUnknown,
        };
    }

    public static function readFailure(int $status): TransportFailure
    {
        $failure = self::failure($status);

        return $failure === TransportFailure::OutcomeUnknown ? TransportFailure::HttpError : $failure;
    }
}
