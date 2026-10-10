<?php

declare(strict_types=1);

namespace Akira\Efatura\Events;

use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Packaging\PreparedDocument;

final readonly class DocumentSubmissionFailed
{
    public function __construct(public PreparedDocument $prepared, public TransportFailure $failure, public bool $retryable) {}
}
