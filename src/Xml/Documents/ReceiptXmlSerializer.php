<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Documents;

use Akira\Efatura\Contracts\DocumentXmlSerializer;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Xml\Serializers\RentReceiptXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class ReceiptXmlSerializer implements DocumentXmlSerializer
{
    public function __construct(
        private DocumentSections $sections,
        private RentReceiptXmlSerializer $rent,
    ) {}

    public function append(XmlWriter $xml, DOMElement $dfe, DocumentData $document): void
    {
        $document = $this->sections->narrow($document, ReceiptData::class, self::class);
        $body     = $this->sections->open($xml, $dfe, $document);

        $this->sections->parties($xml, $body, $document);
        $this->sections->party($xml, $body, 'PaymentParty', $document->paymentParty, 'paymentParty');

        $xml->requiredElement($body, 'ReceiptTypeCode', $document->receiptType->value, 'receiptTypeCode');
        $this->rent->append($xml, $body, $document->rentReceipt, 'rentReceipt');
        $this->sections->references($xml, $body, $document->references);
        $this->sections->payments($xml, $body, $document->payments);
        $this->sections->footer($xml, $body, $document->footer);
    }
}
