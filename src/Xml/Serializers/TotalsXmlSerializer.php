<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml\Serializers;

use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Xml\XmlValue;
use Akira\Efatura\Xml\XmlWriter;
use DOMElement;

final readonly class TotalsXmlSerializer
{
    public function __construct(private DiscountXmlSerializer $discount) {}

    public function append(XmlWriter $xml, DOMElement $parent, TotalsData $totals, string $path): DOMElement
    {
        $element = $xml->container($parent, 'Totals');

        $xml->decimal($element, 'PriceExtensionTotalAmount', $totals->priceExtensionTotalAmount, $path . '.priceExtensionTotalAmount');
        $xml->decimal($element, 'ChargeTotalAmount', $totals->chargeTotalAmount, $path . '.chargeTotalAmount');
        $xml->decimal($element, 'DiscountTotalAmount', $totals->discountTotalAmount, $path . '.discountTotalAmount');
        $xml->decimal($element, 'NetTotalAmount', $totals->netTotalAmount, $path . '.netTotalAmount');

        $this->discount->append($xml, $element, $totals->discount, $path . '.discount');
        $xml->decimal($element, 'TaxTotalAmount', $totals->taxTotalAmount, $path . '.taxTotalAmount');
        $xml->decimal($element, 'WithholdingTaxTotalAmount', $totals->withholdingTaxTotalAmount, $path . '.withholdingTaxTotalAmount');
        $xml->decimal($element, 'PayableRoundingAmount', $totals->payableRoundingAmount, $path . '.payableRoundingAmount');
        $xml->decimal($element, 'PayableAmount', $totals->payableAmount, $path . '.payableAmount');

        foreach ($totals->payableAlternativeAmounts as $index => $alternative) {
            $this->alternative($xml, $element, $alternative, $path . '.payableAlternativeAmounts.' . $index);
        }

        return $element;
    }

    private function alternative(XmlWriter $xml, DOMElement $parent, PayableAlternativeAmountData $alternative, string $path): void
    {
        $element = $xml->requiredElement($parent, 'PayableAlternativeAmount', XmlValue::decimal($alternative->value, $path . '.value'), $path . '.value');
        $xml->attribute($element, 'CurrencyCode', $alternative->currencyCode, $path . '.currencyCode');
        $xml->attribute($element, 'ExchangeRate', XmlValue::decimal($alternative->exchangeRate, $path . '.exchangeRate'), $path . '.exchangeRate');
    }
}
