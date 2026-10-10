<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Documents;

use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Xml\Serializers\HeaderXmlSerializer;
use Akira\Efatura\Xml\Serializers\LineXmlSerializer;
use Akira\Efatura\Xml\Serializers\PartyXmlSerializer;
use Akira\Efatura\Xml\Serializers\PaymentXmlSerializer;
use Akira\Efatura\Xml\Serializers\ReferenceXmlSerializer;
use Akira\Efatura\Xml\Serializers\TotalsXmlSerializer;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use Carbon\CarbonImmutable;
use DOMElement;

final readonly class DocumentSections
{
    public function __construct(
        private HeaderXmlSerializer $header,
        private PartyXmlSerializer $parties,
        private LineXmlSerializer $lines,
        private TotalsXmlSerializer $totals,
        private ReferenceXmlSerializer $references,
        private PaymentXmlSerializer $payments,
    ) {}

    /**
     * @template TDocument of DocumentData
     *
     * @param  class-string<TDocument> $class
     * @return TDocument
     */
    public function narrow(DocumentData $document, string $class, string $serializer): DocumentData
    {
        return $document instanceof $class ? $document : throw DefinitionException::serializerType($serializer, $document::class);
    }

    public function open(XmlWriter $xml, DOMElement $dfe, DocumentData $document): DOMElement
    {
        $body = $xml->container($dfe, $document->type()->xmlElement());
        $this->header->header($xml, $body, $document->header, 'header');

        return $body;
    }

    public function date(XmlWriter $xml, DOMElement $body, string $name, ?CarbonImmutable $date, string $path): void
    {
        $xml->element($body, $name, XmlValue::date($date), $path);
    }

    public function orderReference(XmlWriter $xml, DOMElement $body, ?string $reference): void
    {
        if ($reference !== null) {
            $xml->requiredElement($xml->container($body, 'OrderReference'), 'Id', $reference, 'orderReference');
        }
    }

    public function parties(XmlWriter $xml, DOMElement $body, DocumentData $document): void
    {
        $this->parties->append($xml, $body, 'EmitterParty', $document->emitter, 'emitter');
        $this->parties->append($xml, $body, 'ReceiverParty', $document->receiver, 'receiver');
    }

    public function party(XmlWriter $xml, DOMElement $body, string $name, ?PartyData $party, string $path): void
    {
        $this->parties->append($xml, $body, $name, $party, $path);
    }

    /**
     * @param list<LineItemData> $lines
     */
    public function lines(XmlWriter $xml, DOMElement $body, array $lines): void
    {
        $this->lines->append($xml, $body, $lines, 'lines');
    }

    /**
     * @param list<LineItemData> $lines
     */
    public function linesAndTotals(XmlWriter $xml, DOMElement $body, array $lines, TotalsData $totals): void
    {
        $this->lines($xml, $body, $lines);
        $this->totals->append($xml, $body, $totals, 'totals');
    }

    /**
     * @param list<ReferenceData> $references
     */
    public function references(XmlWriter $xml, DOMElement $body, array $references): void
    {
        $this->references->append($xml, $body, $references, 'references');
    }

    public function payments(XmlWriter $xml, DOMElement $body, ?PaymentsData $payments): void
    {
        $this->payments->append($xml, $body, $payments, 'payments');
    }

    public function invoicePayments(XmlWriter $xml, DOMElement $body, ?PaymentsData $payments): void
    {
        $this->payments->appendInvoice($xml, $body, $payments, 'payments');
    }

    public function delivery(XmlWriter $xml, DOMElement $body, ?DeliveryData $delivery): void
    {
        $this->parties->delivery($xml, $body, $delivery, 'delivery');
    }

    public function footer(XmlWriter $xml, DOMElement $body, ?DocumentFooterData $footer): void
    {
        $this->header->footer($xml, $body, $footer, 'footer');
    }
}
