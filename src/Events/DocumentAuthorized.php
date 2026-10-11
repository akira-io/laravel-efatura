<?php

declare(strict_types=1);

namespace Akira\Efatura\Events;

use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Transport\EntryResult;

final readonly class DocumentAuthorized
{
    public function __construct(public PreparedDocument $prepared, public EntryResult $result) {}
}
