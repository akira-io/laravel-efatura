<?php

declare(strict_types=1);

namespace App\Actions;

use Akira\Efatura\Actions\BuildDfaDataAction;
use Akira\Efatura\Actions\IssueDocumentAction;
use Akira\Efatura\Actions\PrepareDocumentAction;
use Akira\Efatura\Contracts\DfaRenderer;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Dfa\RenderedDfa;
use Akira\Efatura\Enums\EmissionMode;

final readonly class RenderSampleDfaAction
{
    public function __construct(
        private IssueDocumentAction $issueDocument,
        private PrepareDocumentAction $prepareDocument,
        private BuildDfaDataAction $buildDfa,
        private DfaRenderer $renderer,
    ) {}

    public function handle(DocumentData $document): RenderedDfa
    {
        $source = $document->emission?->issueMode === EmissionMode::Online
            ? $this->issueDocument->handle($document)
            : $this->prepareDocument->handle($document);

        return $this->renderer->render($this->buildDfa->handle($source));
    }
}
