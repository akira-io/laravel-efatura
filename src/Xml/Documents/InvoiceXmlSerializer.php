<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Documents;

use Akira\Efatura\Contracts\DocumentXmlSerializer;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class InvoiceXmlSerializer implements DocumentXmlSerializer
{
    public function __construct(private DocumentSections $sections) {}

    public function append(XmlWriter $xml, DOMElement $dfe, DocumentData $document): void
    {
        $document = $this->sections->narrow($document, ElectronicInvoiceData::class, self::class);
        $body     = $this->sections->open($xml, $dfe, $document);

        $this->sections->date($xml, $body, 'DueDate', $document->dueDate, 'dueDate');
        $this->sections->orderReference($xml, $body, $document->orderReference);
        $this->sections->date($xml, $body, 'TaxPointDate', $document->taxPointDate, 'taxPointDate');
        $this->sections->parties($xml, $body, $document);
        $this->sections->linesAndTotals($xml, $body, $document->lines, $document->totals);
        $this->sections->references($xml, $body, $document->references);
        $this->sections->invoicePayments($xml, $body, $document->payments);
        $this->sections->delivery($xml, $body, $document->delivery);
        $this->sections->footer($xml, $body, $document->footer);
    }
}
