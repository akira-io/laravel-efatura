<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\ContingencyData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\SoftwareData;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class TransmissionXmlSerializer
{
    public function __construct(private PartyXmlSerializer $parties) {}

    public function append(XmlWriter $xml, DOMElement $parent, ?EmissionContextData $emission, string $path): DOMElement
    {
        $emission    = $xml->required($emission, $path);
        $transmitter = $xml->required($emission->transmitterTaxId, $path . '.transmitterTaxId');
        $software    = $xml->required($emission->software, $path . '.software');
        $element     = $xml->container($parent, 'Transmission');

        $xml->requiredElement($element, 'IssueMode', XmlValue::integer($emission->issueMode->value), $path . '.issueMode');
        $this->parties->taxId($xml, $element, 'TransmitterTaxId', $transmitter, $path . '.transmitterTaxId');
        $this->software($xml, $element, $software, $path . '.software');
        $this->contingency($xml, $element, $emission->contingency, $path . '.contingency');

        return $element;
    }

    private function software(XmlWriter $xml, DOMElement $parent, SoftwareData $software, string $path): void
    {
        $element = $xml->container($parent, 'Software');

        $xml->requiredElement($element, 'Code', $software->code, $path . '.code');
        $xml->requiredElement($element, 'Name', $software->name, $path . '.name');
        $xml->requiredElement($element, 'Version', $software->version, $path . '.version');
    }

    private function contingency(XmlWriter $xml, DOMElement $parent, ?ContingencyData $contingency, string $path): void
    {
        if (! $contingency instanceof ContingencyData) {
            return;
        }

        $element = $xml->container($parent, 'Contingency');

        $xml->requiredElement($element, 'LedCode', XmlValue::integer($contingency->ledCode), $path . '.ledCode');
        $xml->element($element, 'IUC', $contingency->iuc, $path . '.iuc');
        $xml->requiredElement($element, 'IssueDate', XmlValue::date($contingency->issueDate, instant: true), $path . '.issueDate');
        $xml->element($element, 'IssueTime', XmlValue::time($contingency->issueTime, instant: true), $path . '.issueTime');
        $xml->requiredElement($element, 'ReasonTypeCode', $contingency->reason->value, $path . '.reasonTypeCode');
        $xml->element($element, 'ReasonDescription', $contingency->reasonDescription, $path . '.reasonDescription');
    }
}
