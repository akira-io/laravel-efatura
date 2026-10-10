<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Enums\ReconciliationOutcome;
use Akira\Efatura\Issuance\Reconciliation;

final readonly class ReconcileDocumentAction
{
    public function __construct(private Transport $transport, private ParseIudAction $parseIud) {}

    public function handle(string $iud): Reconciliation
    {
        $this->parseIud->handle($iud);
        $lookup = $this->transport->lookupDocument($iud);

        return new Reconciliation($iud, $lookup->found ? ReconciliationOutcome::Authorized : ReconciliationOutcome::NotFound, $lookup->xml);
    }
}
