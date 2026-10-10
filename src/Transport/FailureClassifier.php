<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PackagedArchive;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use SensitiveParameter;
use Throwable;

final readonly class FailureClassifier
{
    private const int OPERATION_TIMED_OUT = 28;

    private const array UNDELIVERED_ERRORS = [6, 7, 35, 60];

    public function classify(#[SensitiveParameter] ConnectionException $exception, PackagedArchive|string|null $subject): TransportException
    {
        $failure = $this->undelivered($exception) ? TransportFailure::ConnectionFailed : TransportFailure::OutcomeUnknown;

        return TransportException::because($failure, $subject);
    }

    public function undelivered(#[SensitiveParameter] ConnectionException $exception): bool
    {
        $cause   = $exception->getPrevious();
        $context = $cause instanceof Throwable && method_exists($cause, 'getHandlerContext') ? $cause->getHandlerContext() : [];
        $errno   = \is_array($context) ? ($context['errno'] ?? null) : null;
        if (! \is_int($errno)) {
            return $cause instanceof ConnectException;
        }

        $connectTime = $context['connect_time'] ?? null;

        return \in_array($errno, self::UNDELIVERED_ERRORS, true)
            || ($errno === self::OPERATION_TIMED_OUT && (\is_int($connectTime) || \is_float($connectTime)) && $connectTime <= 0);
    }
}
