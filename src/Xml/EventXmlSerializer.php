<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\EventNumberRangeData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\Serializers\PartyXmlSerializer;
use Akira\Efatura\Xml\Serializers\TransmissionXmlSerializer;
use DOMElement;

final readonly class EventXmlSerializer
{
    public function __construct(
        private PartyXmlSerializer $parties,
        private TransmissionXmlSerializer $transmission,
    ) {}

    public function append(XmlWriter $xml, EventData $event, string $eventId, Environment $repository): DOMElement
    {
        $root = $xml->document('Event');
        $xml->attribute($root, 'Id', $eventId, 'eventId');
        $xml->attribute($root, 'Version', Fiscal::XML_SCHEMA_VERSION, 'version');
        $xml->attribute($root, 'EventTypeCode', $event->eventType->value, 'eventTypeCode');

        $this->parties->taxId($xml, $root, 'EmitterTaxId', $event->emitterTaxId, 'emitterTaxId');
        $xml->requiredElement($root, 'IssueDateTime', XmlValue::dateTime($event->issueDateTime, instant: true), 'issueDateTime');
        $xml->requiredElement($root, 'IssueReasonDescription', $event->issueReasonDescription, 'issueReasonDescription');

        foreach ($event->iuds as $index => $iud) {
            $xml->requiredElement($root, 'IUD', $iud, 'iuds.' . $index);
        }

        $this->numberRange($xml, $root, $event->numberRange);
        $this->transmission->append($xml, $root, $event->emission, 'emission');
        $xml->requiredElement($root, 'RepositoryCode', XmlValue::integer($repository->value), 'repositoryCode');

        return $root;
    }

    private function numberRange(XmlWriter $xml, DOMElement $root, ?EventNumberRangeData $range): void
    {
        if (! $range instanceof EventNumberRangeData) {
            return;
        }

        $xml->elements($root, 'numberRange', [
            'Year'                => ['year', XmlValue::integer($range->year)],
            'LedCode'             => ['ledCode', XmlValue::integer($range->ledCode)],
            'Serie'               => ['serie', $range->series],
            'DocumentTypeCode'    => ['documentTypeCode', XmlValue::integer($range->documentType->code())],
            'DocumentNumberStart' => ['documentNumberStart', XmlValue::integer($range->documentNumberStart)],
            'DocumentNumberEnd'   => ['documentNumberEnd', XmlValue::integer($range->documentNumberEnd)],
        ]);
    }
}
