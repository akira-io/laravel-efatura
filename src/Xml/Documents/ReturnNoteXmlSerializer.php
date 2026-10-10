<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Documents;

use Akira\Efatura\Contracts\DocumentXmlSerializer;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class ReturnNoteXmlSerializer implements DocumentXmlSerializer
{
    public function __construct(private DocumentSections $sections) {}

    public function append(XmlWriter $xml, DOMElement $dfe, DocumentData $document): void
    {
        $document = $this->sections->narrow($document, ReturnNoteData::class, self::class);
        $body     = $this->sections->open($xml, $dfe, $document);

        $xml->requiredElement($body, 'IssueReasonCode', $document->issueReason->value, 'issueReasonCode');
        $xml->element($body, 'IssueReasonDescription', $document->issueReasonDescription, 'issueReasonDescription');

        $this->sections->parties($xml, $body, $document);
        $this->sections->linesAndTotals($xml, $body, $document->lines, $document->totals);
        $this->sections->references($xml, $body, $document->references);
        $this->sections->footer($xml, $body, $document->footer);
    }
}
