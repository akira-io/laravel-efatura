<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Events\DocumentAuthorized;
use Akira\Efatura\Events\DocumentPrepared;
use Akira\Efatura\Events\DocumentRejected;
use Akira\Efatura\Events\DocumentSubmissionFailed;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Issuance\IssuanceAnnouncer;
use Akira\Efatura\Issuance\IssuedDocument;

final readonly class IssueDocumentAction
{
    public function __construct(
        private PrepareDocumentAction $prepareDocument,
        private SubmitPreparedDocumentAction $submitDocument,
        private IssuanceAnnouncer $announcer,
    ) {}

    public function handle(
        DocumentData $document,
        bool $isSpecimen = false,
        SignatureProfile $profile = SignatureProfile::Enveloped,
        ?string $iud = null,
    ): IssuedDocument {
        $prepared = $this->prepareDocument->handle($document, $isSpecimen, $profile, $iud);
        $this->announcer->beforeSubmission($prepared, new DocumentPrepared($prepared));

        try {
            $issued = $this->submitDocument->handle($prepared);
        } catch (TransportException $transportException) {
            $this->announcer->afterSubmission(new DocumentSubmissionFailed($prepared, $transportException->failure, $transportException->retryable));

            throw $transportException;
        }

        $this->announcer->afterSubmission(
            $issued->authorized() ? new DocumentAuthorized($prepared, $issued->result) : new DocumentRejected($prepared, $issued->result),
        );

        return $issued;
    }
}
