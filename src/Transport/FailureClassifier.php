<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PackagedArchive;
use Illuminate\Http\Client\ConnectionException;
use SensitiveParameter;
use Throwable;

final readonly class FailureClassifier
{
    private const int OPERATION_TIMED_OUT = 28;

    private const array UNDELIVERED_ERRORS = [6, 7, 35, 60];

    private const string CONNECT_TIMEOUT = 'GuzzleHttp\Exception\ConnectTimeoutException';

    private const string REPORTED_ERROR = '/^cURL error (\d+):/';

    public function classify(#[SensitiveParameter] ConnectionException $exception, PackagedArchive|string|null $subject): TransportException
    {
        $failure = $this->undelivered($exception) ? TransportFailure::ConnectionFailed : TransportFailure::OutcomeUnknown;

        return TransportException::because($failure, $subject);
    }

    public function undelivered(#[SensitiveParameter] ConnectionException $exception): bool
    {
        $cause = $exception->getPrevious();
        if (! $cause instanceof Throwable) {
            return false;
        }

        return is_a($cause, self::CONNECT_TIMEOUT) || $this->undeliveredError($this->handlerContext($cause), $cause);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    private function undeliveredError(array $context, #[SensitiveParameter] Throwable $cause): bool
    {
        $errno = $context['errno'] ?? $this->reportedError($cause);
        if (! \is_int($errno)) {
            return false;
        }

        $connectTime = $context['connect_time'] ?? null;

        return \in_array($errno, self::UNDELIVERED_ERRORS, true)
            || ($errno === self::OPERATION_TIMED_OUT && (\is_int($connectTime) || \is_float($connectTime)) && $connectTime <= 0);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function handlerContext(#[SensitiveParameter] Throwable $cause): array
    {
        $context = method_exists($cause, 'getHandlerContext') ? $cause->getHandlerContext() : [];

        return \is_array($context) ? $context : [];
    }

    private function reportedError(#[SensitiveParameter] Throwable $cause): ?int
    {
        return preg_match(self::REPORTED_ERROR, $cause->getMessage(), $match) === 1 ? (int) $match[1] : null;
    }
}
