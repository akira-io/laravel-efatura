<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Documents;

use Akira\Efatura\Contracts\DocumentXmlSerializer;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class SalesReceiptXmlSerializer implements DocumentXmlSerializer
{
    public function __construct(private DocumentSections $sections) {}

    public function append(XmlWriter $xml, DOMElement $dfe, DocumentData $document): void
    {
        $document = $this->sections->narrow($document, SalesReceiptData::class, self::class);
        $body     = $this->sections->open($xml, $dfe, $document);

        $this->sections->parties($xml, $body, $document);
        $this->sections->linesAndTotals($xml, $body, $document->lines, $document->totals);
        $this->sections->payments($xml, $body, $document->payments);
        $this->sections->delivery($xml, $body, $document->delivery);
        $this->sections->footer($xml, $body, $document->footer);
    }
}
