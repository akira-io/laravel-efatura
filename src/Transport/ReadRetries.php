<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Configuration\HttpClientConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use SensitiveParameter;
use Throwable;

final readonly class ReadRetries
{
    private const array RETRYABLE_STATUSES = [429, 502, 503, 504];

    public function __construct(private FailureClassifier $failures) {}

    public function apply(#[SensitiveParameter] PendingRequest $request, HttpClientConfig $config): PendingRequest
    {
        return $request->retry($config->retries + 1, $config->retryDelayMilliseconds, $this->retryable(...), throw: false);
    }

    public function retryable(#[SensitiveParameter] ?Throwable $exception): bool
    {
        return match (true) {
            $exception instanceof ConnectionException => $this->failures->undelivered($exception),
            $exception instanceof RequestException    => \in_array($exception->response->status(), self::RETRYABLE_STATUSES, true),
            default                                   => false,
        };
    }
}
