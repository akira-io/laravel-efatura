<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\ExtraPropertyData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Data\StandardIdentificationData;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class LineXmlSerializer
{
    public function __construct(
        private TaxXmlSerializer $taxes,
        private DiscountXmlSerializer $discount,
    ) {}

    /**
     * @param list<LineItemData> $lines
     */
    public function append(XmlWriter $xml, DOMElement $parent, array $lines, string $path): DOMElement
    {
        $element = $xml->container($parent, 'Lines');

        foreach ($lines as $index => $line) {
            $this->line($xml, $element, $line, $path . '.' . $index);
        }

        return $element;
    }

    public function quantity(XmlWriter $xml, DOMElement $parent, string $name, ?QuantityData $quantity, string $path): ?DOMElement
    {
        if (! $quantity instanceof QuantityData) {
            return null;
        }

        $element = $xml->requiredElement($parent, $name, XmlValue::decimal($quantity->value, $path . '.value'), $path . '.value');
        $xml->attribute($element, 'UnitCode', $quantity->unitCode, $path . '.unitCode');
        $xml->attribute($element, 'IsStandardUnitCode', XmlValue::boolean($quantity->isStandardUnitCode), $path . '.isStandardUnitCode');

        return $element;
    }

    private function line(XmlWriter $xml, DOMElement $parent, LineItemData $line, string $path): void
    {
        $element = $xml->container($parent, 'Line');
        $xml->attribute($element, 'LineTypeCode', $line->lineType->value, $path . '.lineTypeCode');

        $xml->elements($element, $path, [
            'Id'                 => ['id', $line->id],
            'LineReferenceId'    => ['lineReferenceId', $line->lineReferenceId],
            'OrderLineReference' => ['orderLineReference', XmlValue::integer($line->orderLineReference)],
        ]);
        $this->quantity($xml, $element, 'Quantity', $line->quantity, $path . '.quantity');
        $xml->decimal($element, 'Price', $line->price, $path . '.price');
        $xml->decimal($element, 'PriceExtension', $line->priceExtension, $path . '.priceExtension');

        $this->discount->append($xml, $element, $line->discount, $path . '.discount');
        $xml->decimal($element, 'NetTotal', $line->netTotal, $path . '.netTotal');
        $this->taxes->appendAll($xml, $element, $line->taxes, $path . '.taxes');
        $this->item($xml, $element, $line->item, $path . '.item');
    }

    private function item(XmlWriter $xml, DOMElement $parent, ItemData $item, string $path): void
    {
        $element = $xml->container($parent, 'Item');

        $xml->requiredElement($element, 'Description', $item->description, $path . '.description');
        $this->quantity($xml, $element, 'PackQuantity', $item->packQuantity, $path . '.packQuantity');
        $xml->elements($element, $path, [
            'Name'      => ['name', $item->name],
            'BrandName' => ['brandName', $item->brandName],
            'ModelName' => ['modelName', $item->modelName],
        ]);
        $xml->requiredElement($element, 'EmitterIdentification', $item->emitterIdentification, $path . '.emitterIdentification');
        $this->standardIdentification($xml, $element, $item->standardIdentification, $path . '.standardIdentification');
        $xml->element($element, 'HazardousRiskIndicator', XmlValue::boolean($item->hazardousRiskIndicator), $path . '.hazardousRiskIndicator');
        $this->extraProperties($xml, $element, $item->extraProperties, $path . '.extraProperties');
    }

    private function standardIdentification(XmlWriter $xml, DOMElement $parent, ?StandardIdentificationData $identification, string $path): void
    {
        if (! $identification instanceof StandardIdentificationData) {
            return;
        }

        $xml->elements($xml->container($parent, 'StandardIdentification'), $path, [
            'GTIN'       => ['gtin', $identification->gtin],
            'EAN'        => ['ean', $identification->ean],
            'UPC'        => ['upc', $identification->upc],
            'Pharmacode' => ['pharmacode', $identification->pharmacode],
        ]);
    }

    /**
     * @param list<ExtraPropertyData> $properties
     */
    private function extraProperties(XmlWriter $xml, DOMElement $parent, array $properties, string $path): void
    {
        if ($properties === []) {
            return;
        }

        $element = $xml->container($parent, 'ExtraProperties');

        foreach ($properties as $index => $property) {
            $node = $xml->requiredElement($element, 'Property', $property->value, $path . '.' . $index . '.value');
            $xml->attribute($node, 'Name', $property->name, $path . '.' . $index . '.name');
        }
    }
}
