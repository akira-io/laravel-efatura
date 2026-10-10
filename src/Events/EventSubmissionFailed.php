<?php

declare(strict_types=1);

namespace Akira\Efatura\Events;

use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Packaging\PreparedEvent;

final readonly class EventSubmissionFailed
{
    public function __construct(public PreparedEvent $prepared, public TransportFailure $failure, public bool $retryable) {}
}
