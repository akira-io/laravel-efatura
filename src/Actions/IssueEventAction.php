<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\EventData;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Events\EventAuthorized;
use Akira\Efatura\Events\EventPrepared;
use Akira\Efatura\Events\EventRejected;
use Akira\Efatura\Events\EventSubmissionFailed;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Issuance\IssuanceAnnouncer;
use Akira\Efatura\Issuance\IssuedEvent;

final readonly class IssueEventAction
{
    public function __construct(
        private PrepareEventAction $prepareEvent,
        private SubmitPreparedEventAction $submitEvent,
        private IssuanceAnnouncer $announcer,
    ) {}

    public function handle(EventData $event, SignatureProfile $profile = SignatureProfile::Enveloped): IssuedEvent
    {
        $prepared = $this->prepareEvent->handle($event, $profile);
        $this->announcer->beforeSubmission($prepared, new EventPrepared($prepared));

        try {
            $issued = $this->submitEvent->handle($prepared);
        } catch (EfaturaException $efaturaException) {
            $this->announcer->afterSubmission(EventSubmissionFailed::because($prepared, $efaturaException));

            throw $efaturaException;
        }

        $this->announcer->afterSubmission(
            $issued->authorized() ? new EventAuthorized($prepared, $issued->result) : new EventRejected($prepared, $issued->result),
        );

        return $issued;
    }
}
