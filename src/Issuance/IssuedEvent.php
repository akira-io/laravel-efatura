<?php

declare(strict_types=1);

namespace Akira\Efatura\Issuance;

use Akira\Efatura\Packaging\PreparedEvent;
use Akira\Efatura\Transport\EntryResult;
use Akira\Efatura\Transport\SubmissionResult;

final readonly class IssuedEvent
{
    public function __construct(
        public PreparedEvent $prepared,
        public EntryResult $result,
        public SubmissionResult $submission,
    ) {}

    public function authorized(): bool
    {
        return $this->result->succeeded;
    }
}
