<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\ReconciliationOutcome;
use Akira\Efatura\Issuance\Reconciliation;
use Akira\Efatura\Packaging\PreparedDocument;

final readonly class ReconcileDocumentAction
{
    public function __construct(private Transport $transport, private ParseIudAction $parseIud) {}

    public function handle(PreparedDocument|string $document, bool $selfBilled = false): Reconciliation
    {
        $iud        = $document instanceof PreparedDocument ? $document->iud : $document;
        $selfBilled = $document instanceof PreparedDocument ? $document->archive->selfBilled : $selfBilled;
        $this->parseIud->handle($iud);
        $lookup = $this->transport->lookupDocument($iud, $selfBilled);

        return new Reconciliation($iud, $lookup->found ? ReconciliationOutcome::Authorized : ReconciliationOutcome::NotFound, $lookup->xml);
    }
}
