<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\EventIdData;
use Akira\Efatura\Enums\EventIdSegment;
use Akira\Efatura\Support\FiscalRules;
use Illuminate\Validation\ValidationException;

final readonly class ParseEventIdAction
{
    public function handle(string $eventId, string $field = 'eventId'): EventIdData
    {
        if (! FiscalRules::isEventId($eventId)) {
            throw self::invalid($field);
        }

        try {
            return EventIdData::from([
                'repositoryCode' => (int) EventIdSegment::Repository->of($eventId),
                'issueDateTime'  => vsprintf('20%s-%s-%sT%s:%s:%s', str_split(EventIdSegment::IssueDateTime->of($eventId), 2)),
                'taxId'          => EventIdSegment::TaxId->of($eventId),
            ]);
        } catch (ValidationException) {
            throw self::invalid($field);
        }
    }

    private static function invalid(string $field): ValidationException
    {
        return ValidationException::withMessages([$field => __('efatura::efatura.validation.event_id_invalid', ['attribute' => $field])]);
    }
}
