<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class TaxXmlSerializer
{
    public function append(XmlWriter $xml, DOMElement $parent, TaxData $tax, string $path): DOMElement
    {
        $element = $xml->container($parent, 'Tax');
        $xml->attribute($element, 'TaxTypeCode', $tax->taxType->value, $path . '.taxTypeCode');

        $xml->element($element, 'StampTaxCode', XmlValue::integer($tax->stampTaxCode?->value), $path . '.stampTaxCode');
        $xml->decimal($element, 'TaxPercentage', $tax->taxPercentage, $path . '.taxPercentage', Fiscal::PERCENTAGE_SCALE);
        $xml->decimal($element, 'TaxAmount', $tax->taxAmount, $path . '.taxAmount');
        $xml->element($element, 'TaxExemptionReasonCode', $tax->taxExemptionReasonCode, $path . '.taxExemptionReasonCode');
        $xml->decimal($element, 'TaxTotal', $tax->taxTotal, $path . '.taxTotal');

        return $element;
    }

    /**
     * @param list<TaxData> $taxes
     */
    public function appendAll(XmlWriter $xml, DOMElement $parent, array $taxes, string $path): void
    {
        foreach ($taxes as $index => $tax) {
            $this->append($xml, $parent, $tax, $path . '.' . $index);
        }
    }
}
