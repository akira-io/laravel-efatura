<?php

declare(strict_types=1);

namespace Akira\Efatura\Issuance;

use Akira\Efatura\Exceptions\IssuanceException;
use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Packaging\PreparedEvent;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final readonly class IssuanceAnnouncer
{
    public function __construct(private Dispatcher $events, private ExceptionHandler $exceptions) {}

    public function beforeSubmission(PreparedDocument|PreparedEvent $prepared, object $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (Throwable $throwable) {
            throw IssuanceException::abortedBeforeSubmission($prepared, $throwable);
        }
    }

    public function afterSubmission(object $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (Throwable $throwable) {
            $this->exceptions->report($throwable);
        }
    }
}
