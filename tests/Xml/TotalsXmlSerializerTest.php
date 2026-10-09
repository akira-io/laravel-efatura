<?php

declare(strict_types=1);

use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Tests\Support\DocumentFixtures;
use Akira\Efatura\Tests\Support\XmlFragment;
use Akira\Efatura\Tests\Support\XmlPartFixtures;
use Akira\Efatura\Xml\Serializers\TotalsXmlSerializer;
use Akira\Efatura\Xml\XmlWriter;
use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;

it('writes the required totals in schema order', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TotalsXmlSerializer::class)
        ->append($xml, $root, DocumentFixtures::totals(), 'totals')))->toBe(
            '<Totals><PriceExtensionTotalAmount>100</PriceExtensionTotalAmount><NetTotalAmount>100</NetTotalAmount>'
            . '<TaxTotalAmount>15</TaxTotalAmount><PayableAmount>115</PayableAmount></Totals>',
        );
});

it('writes withholding, a negative rounding and alternative amounts in schema order', function (): void {
    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TotalsXmlSerializer::class)
        ->append($xml, $root, TotalsData::from(XmlPartFixtures::fullTotals()), 'totals')))->toBe(
            '<Totals><PriceExtensionTotalAmount>100</PriceExtensionTotalAmount><ChargeTotalAmount>10</ChargeTotalAmount>'
            . '<DiscountTotalAmount>5.5</DiscountTotalAmount><NetTotalAmount>100</NetTotalAmount><Discount ValueType="P">5.5</Discount>'
            . '<TaxTotalAmount>15</TaxTotalAmount><WithholdingTaxTotalAmount>10</WithholdingTaxTotalAmount>'
            . '<PayableRoundingAmount>-0.01</PayableRoundingAmount><PayableAmount>104.99</PayableAmount>'
            . '<PayableAlternativeAmount CurrencyCode="EUR" ExchangeRate="110.265">0.95218</PayableAlternativeAmount>'
            . '<PayableAlternativeAmount CurrencyCode="USD" ExchangeRate="100.5">1.04468</PayableAlternativeAmount></Totals>',
        );
});

it('writes a document discount given as an amount', function (): void {
    $totals = DocumentFixtures::totals(['discount' => ['value' => '2.12345', 'valueType' => 'A']]);

    expect(XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TotalsXmlSerializer::class)->append($xml, $root, $totals, 'totals')))
        ->toContain('<NetTotalAmount>100</NetTotalAmount><Discount ValueType="A">2.12345</Discount><TaxTotalAmount>');
});

it('reports an unwritable alternative amount on its wire path', function (): void {
    $totals = new TotalsData(
        Money::of('100', 'CVE', new CustomContext(5)),
        Money::of('100', 'CVE', new CustomContext(5)),
        Money::of('15', 'CVE', new CustomContext(5)),
        Money::of('115', 'CVE', new CustomContext(5)),
        payableAlternativeAmounts: [
            new PayableAlternativeAmountData(Money::of('1.123456', 'EUR', new CustomContext(6)), 'EUR', BigDecimal::of('110')),
        ],
    );

    expect(fn (): string => XmlFragment::of(fn (XmlWriter $xml, DOMElement $root): DOMElement => resolve(TotalsXmlSerializer::class)->append($xml, $root, $totals, 'totals')))
        ->toFailValidationOn('totals.payableAlternativeAmounts.0.value', 'Value exceeds the allowed decimal precision.');
});
