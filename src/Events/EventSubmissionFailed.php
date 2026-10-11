<?php

declare(strict_types=1);

namespace Akira\Efatura\Events;

use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PreparedEvent;

final readonly class EventSubmissionFailed
{
    public function __construct(public PreparedEvent $prepared, public string $errorCode, public bool $retryable, public bool $mayHaveBeenProcessed) {}

    public static function because(PreparedEvent $prepared, EfaturaException $exception): self
    {
        $processed = $exception instanceof TransportException && $exception->failure->mayHaveBeenProcessed();

        return new self($prepared, $exception->errorCode, $exception->retryable, $processed);
    }
}
