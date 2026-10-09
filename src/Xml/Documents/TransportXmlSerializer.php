<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Documents;

use Akira\Efatura\Contracts\DocumentXmlSerializer;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Xml\Serializers\TransportRouteXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class TransportXmlSerializer implements DocumentXmlSerializer
{
    public function __construct(
        private DocumentSections $sections,
        private TransportRouteXmlSerializer $route,
    ) {}

    public function append(XmlWriter $xml, DOMElement $dfe, DocumentData $document): void
    {
        $document = $this->sections->narrow($document, TransportDocumentData::class, self::class);
        $body     = $this->sections->open($xml, $dfe, $document);

        $xml->element($body, 'ReceiverTypeCode', $document->receiverType?->value, 'receiverTypeCode');
        $xml->requiredElement($body, 'TransportDocumentTypeCode', $document->transportDocumentType->value, 'transportDocumentTypeCode');

        $this->sections->parties($xml, $body, $document);
        $this->sections->party($xml, $body, 'TransportServiceProviderParty', $document->transportServiceProvider, 'transportServiceProvider');
        $this->sections->lines($xml, $body, $document->lines);

        $this->route->append($xml, $body, $document->transportRoute, 'transportRoute');
        $this->sections->references($xml, $body, $document->references);
        $this->sections->footer($xml, $body, $document->footer);
    }
}
