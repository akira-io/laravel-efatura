<?php

declare(strict_types=1);

use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Tests\Support\DocumentFixtures;
use Akira\Efatura\Tests\Support\XmlFragment;
use Akira\Efatura\Tests\Support\XmlPartFixtures;
use Akira\Efatura\Xml\Serializers\LineXmlSerializer;
use Akira\Efatura\Xml\Serializers\TaxXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;
use Brick\Math\BigDecimal;

it('writes a normal line with its type attribute and default unit flag', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(LineXmlSerializer::class)
        ->append($xml, $root, [DocumentFixtures::line()], 'lines')))->toBe(
            '<Lines><Line LineTypeCode="N"><Quantity UnitCode="C62" IsStandardUnitCode="false">1</Quantity>'
            . '<Price>100</Price><PriceExtension>100</PriceExtension><NetTotal>100</NetTotal>'
            . '<Tax TaxTypeCode="IVA"><TaxPercentage>15</TaxPercentage></Tax>'
            . '<Item><Description>Product</Description><EmitterIdentification>SKU</EmitterIdentification></Item></Line></Lines>',
        );
});

it('writes every line and item field in schema order', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(LineXmlSerializer::class)
        ->append($xml, $root, [LineItemData::from(XmlPartFixtures::chargeLine())], 'lines')))->toBe(
            '<Lines><Line LineTypeCode="C"><Id>L2</Id><LineReferenceId>L1</LineReferenceId><OrderLineReference>7</OrderLineReference>'
            . '<Quantity UnitCode="C62" IsStandardUnitCode="true">2.5</Quantity><Price>40</Price><PriceExtension>100</PriceExtension>'
            . '<Discount ValueType="P">12.125</Discount><NetTotal>87.875</NetTotal>'
            . '<Tax TaxTypeCode="IVA"><TaxPercentage>15</TaxPercentage><TaxTotal>13.18125</TaxTotal></Tax>'
            . '<Tax TaxTypeCode="IR"><TaxPercentage>10</TaxPercentage></Tax>'
            . '<Item><Description>Product &lt;A&amp;B&gt;</Description><PackQuantity UnitCode="C62" IsStandardUnitCode="false">6</PackQuantity>'
            . '<Name>Pack</Name><BrandName>Brand</BrandName><ModelName>Model</ModelName><EmitterIdentification>SKU</EmitterIdentification>'
            . '<StandardIdentification><GTIN>0123</GTIN></StandardIdentification><HazardousRiskIndicator>false</HazardousRiskIndicator>'
            . '<ExtraProperties><Property Name="Colour">Red &amp; "blue" &lt;dark&gt;</Property><Property Name="Weight">12</Property></ExtraProperties>'
            . '</Item></Line></Lines>',
        );
});

it('writes the standard identification chosen by the item', function (string $field, string $element): void {
    $line = DocumentFixtures::line(['item' => ['description' => 'Product', 'emitterIdentification' => 'SKU', 'standardIdentification' => [$field => '42']]]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(LineXmlSerializer::class)->append($xml, $root, [$line], 'lines')))
        ->toContain('<StandardIdentification><' . $element . '>42</' . $element . '></StandardIdentification>');
})->with([
    ['gtin', 'GTIN'],
    ['ean', 'EAN'],
    ['upc', 'UPC'],
    ['pharmacode', 'Pharmacode'],
]);

it('writes a line discount at the scale of its value type', function (array $discount, string $expected): void {
    $line = DocumentFixtures::line(['discount' => $discount]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(LineXmlSerializer::class)->append($xml, $root, [$line], 'lines')))
        ->toContain($expected);
})->with([
    'percentage' => [['value' => '12.125', 'valueType' => 'P'], '<Discount ValueType="P">12.125</Discount>'],
    'amount'     => [['value' => '10.12345', 'valueType' => 'A'], '<Discount ValueType="A">10.12345</Discount>'],
]);

it('writes each tax choice with its optional stamp code and total', function (array $tax, string $expected): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TaxXmlSerializer::class)
        ->append($xml, $root, TaxData::from($tax), 'lines.0.taxes.0')))->toBe($expected);
})->with([
    'percentage without total' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15.500'], '<Tax TaxTypeCode="IVA"><TaxPercentage>15.5</TaxPercentage></Tax>'],
    'percentage with total'    => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15', 'taxTotal' => '15'], '<Tax TaxTypeCode="IVA"><TaxPercentage>15</TaxPercentage><TaxTotal>15</TaxTotal></Tax>'],
    'exemption'                => [['taxTypeCode' => 'NA', 'taxExemptionReasonCode' => '1'], '<Tax TaxTypeCode="NA"><TaxExemptionReasonCode>1</TaxExemptionReasonCode></Tax>'],
    'stamp tax amount'         => [
        ['taxTypeCode' => 'IS', 'stampTaxCode' => 7, 'taxAmount' => '0.50000', 'taxTotal' => '0.5'],
        '<Tax TaxTypeCode="IS"><StampTaxCode>7</StampTaxCode><TaxAmount>0.5</TaxAmount><TaxTotal>0.5</TaxTotal></Tax>',
    ],
]);

it('reports invalid line text on the wire path of the line', function (): void {
    $lines = [
        DocumentFixtures::line(),
        new LineItemData(new QuantityData(BigDecimal::one(), 'C62'), new ItemData("Pro\x02duct", 'SKU')),
    ];

    expect(fn (): string => XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(LineXmlSerializer::class)->append($xml, $root, $lines, 'lines')))
        ->toFailValidationOn('lines.1.item.description', 'The lines.1.item.description contains characters that XML 1.0 does not allow.');
});

it('reports an unwritable tax percentage on the wire path of the tax', function (): void {
    $line = new LineItemData(
        new QuantityData(BigDecimal::one(), 'C62'),
        new ItemData('Product', 'SKU'),
        taxes: [new TaxData(TaxType::ValueAddedTax, BigDecimal::of('15.1234'))],
    );

    expect(fn (): string => XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(LineXmlSerializer::class)->append($xml, $root, [$line], 'lines')))
        ->toFailValidationOn('lines.0.taxes.0.taxPercentage', 'Value exceeds the allowed decimal precision.');
});

it('reports an unwritable percentage discount on the wire path of the discount', function (): void {
    $line = new LineItemData(new QuantityData(BigDecimal::one(), 'C62'), new ItemData('Product', 'SKU'), discount: new DiscountData(BigDecimal::of('1.123456')));

    expect(fn (): string => XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(LineXmlSerializer::class)->append($xml, $root, [$line], 'lines')))
        ->toFailValidationOn('lines.0.discount.value', 'Value exceeds the allowed decimal precision.');
});
