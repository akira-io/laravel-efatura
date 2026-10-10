<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\FiscalDocumentData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class ReferenceXmlSerializer
{
    public function __construct(private TaxXmlSerializer $taxes) {}

    /**
     * @param list<ReferenceData> $references
     */
    public function append(XmlWriter $xml, DOMElement $parent, array $references, string $path): ?DOMElement
    {
        if ($references === []) {
            return null;
        }

        $element = $xml->container($parent, 'References');

        foreach ($references as $index => $reference) {
            $this->reference($xml, $xml->container($element, 'Reference'), $reference, $path . '.' . $index);
        }

        return $element;
    }

    private function reference(XmlWriter $xml, DOMElement $element, ReferenceData $reference, string $path): void
    {
        $this->fiscalDocument($xml, $element, $reference->fiscalDocument, $path . '.fiscalDocument');
        $xml->element($element, 'InnerDocumentNumber', $reference->innerDocumentNumber, $path . '.innerDocumentNumber');
        $xml->decimal($element, 'PaymentAmount', $reference->paymentAmount, $path . '.paymentAmount');

        $this->taxes->appendAll($xml, $element, $reference->taxes, $path . '.taxes');
    }

    private function fiscalDocument(XmlWriter $xml, DOMElement $parent, ?FiscalDocumentData $document, string $path): void
    {
        if (! $document instanceof FiscalDocumentData) {
            return;
        }

        $element = $xml->requiredElement($parent, 'FiscalDocument', $document->value, $path . '.value');
        $xml->attribute($element, 'IsOldDocument', XmlValue::boolean($document->isOldDocument), $path . '.isOldDocument');
    }
}
