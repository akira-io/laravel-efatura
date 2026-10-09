<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class DiscountXmlSerializer
{
    public function append(XmlWriter $xml, DOMElement $parent, ?DiscountData $discount, string $path): ?DOMElement
    {
        if (! $discount instanceof DiscountData) {
            return null;
        }

        $element = $xml->requiredElement($parent, 'Discount', XmlValue::decimal($discount->value, $path . '.value'), $path . '.value');
        $xml->attribute($element, 'ValueType', $discount->valueType->value, $path . '.valueType');

        return $element;
    }
}
