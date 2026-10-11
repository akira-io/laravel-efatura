<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Issuance\IssuedEvent;
use Akira\Efatura\Packaging\PreparedEvent;

final readonly class SubmitPreparedEventAction
{
    public function __construct(private SubmitArchiveAction $submitArchive) {}

    public function handle(PreparedEvent $prepared): IssuedEvent
    {
        $submission = $this->submitArchive->handle($prepared->archive);

        return new IssuedEvent($prepared, $submission->entry($prepared->eventId), $submission);
    }
}
