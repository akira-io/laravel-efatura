<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\RentReceiptData;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class RentReceiptXmlSerializer
{
    public function __construct(private PartyXmlSerializer $parties) {}

    public function append(XmlWriter $xml, DOMElement $parent, ?RentReceiptData $rent, string $path): ?DOMElement
    {
        if (! $rent instanceof RentReceiptData) {
            return null;
        }

        $element = $xml->container($parent, 'RentReceipt');

        $xml->requiredElement($element, 'AssetId', $rent->assetId, $path . '.assetId');
        $xml->requiredElement($element, 'RentPurposeTypeCode', $rent->rentPurpose->value, $path . '.rentPurposeTypeCode');
        $xml->requiredElement($element, 'ContractTypeCode', $rent->contractType->value, $path . '.contractTypeCode');
        $xml->requiredElement($element, 'RentTypeCode', $rent->rentType->value, $path . '.rentTypeCode');
        $xml->requiredElement($element, 'ReferencePeriod', $rent->referencePeriod, $path . '.referencePeriod');

        $this->parties->address($xml, $element, $rent->address, $path . '.address');

        return $element;
    }
}
