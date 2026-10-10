<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Issuance\IssuedDocument;
use Akira\Efatura\Packaging\PreparedDocument;

final readonly class SubmitPreparedDocumentAction
{
    public function __construct(private SubmitArchiveAction $submitArchive) {}

    public function handle(PreparedDocument $prepared): IssuedDocument
    {
        $submission = $this->submitArchive->handle($prepared->archive);

        return new IssuedDocument($prepared, $submission->entry($prepared->iud), $submission);
    }
}
