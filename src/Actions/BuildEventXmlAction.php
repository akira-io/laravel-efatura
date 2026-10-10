<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\EventData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\EventXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;
use Illuminate\Validation\ValidationException;

final readonly class BuildEventXmlAction
{
    public function __construct(
        private EventXmlSerializer $serializer,
        private ParseEventIdAction $parseEventId,
    ) {}

    public function handle(EventData $event, string $eventId, Environment $repository): string
    {
        $event = EventData::from($event);
        $xml   = new XmlWriter;

        $this->verifyIdentifier($xml, $event, $eventId, $repository);
        $this->serializer->append($xml, $event, $eventId, $repository);

        return $xml->toXml();
    }

    private function verifyIdentifier(XmlWriter $xml, EventData $event, string $eventId, Environment $repository): void
    {
        $emission    = $xml->required($event->emission, 'emission');
        $transmitter = $xml->required($emission->transmitterTaxId, 'emission.transmitterTaxId');
        $identifier  = $this->parseEventId->handle($eventId);

        $matches = $identifier->repository === $repository
            && Fiscal::format($identifier->issueDateTime, Fiscal::DATE_TIME_FORMAT, instant: true)
                === Fiscal::format($event->issueDateTime, Fiscal::DATE_TIME_FORMAT, instant: true)
            && $identifier->taxId === $transmitter->value;

        if (! $matches) {
            throw ValidationException::withMessages(['eventId' => __('efatura::efatura.validation.event_id_mismatch', ['attribute' => 'eventId'])]);
        }
    }
}
