<?php

declare(strict_types=1);

namespace Akira\Efatura\Issuance;

use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Transport\EntryResult;
use Akira\Efatura\Transport\SubmissionResult;

final readonly class IssuedDocument
{
    public function __construct(
        public PreparedDocument $prepared,
        public EntryResult $result,
        public SubmissionResult $submission,
    ) {}

    public function authorized(): bool
    {
        return $this->result->succeeded;
    }
}
