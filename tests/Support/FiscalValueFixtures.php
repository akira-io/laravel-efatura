<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Data\ContingencyData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Data\PaymentData;
use Akira\Efatura\Data\QuantityData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\SelfBillingData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\FiscalMoney;
use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;

final class FiscalValueFixtures
{
    /**
     * @return array<string, array{class-string, array<string, mixed>, string}>
     */
    public static function moneyOwners(): array
    {
        $line   = ['quantity' => ['value' => '1', 'unitCode' => 'C62'], 'item' => ['description' => 'Item', 'emitterIdentification' => 'SKU']];
        $totals = ['priceExtensionTotalAmount' => '0', 'netTotalAmount' => '0', 'taxTotalAmount' => '0', 'payableAmount' => '0'];

        return [
            'tax amount'               => [TaxData::class, ['taxTypeCode' => 'IVA'], 'taxAmount'],
            'tax total'                => [TaxData::class, ['taxTypeCode' => 'IVA', 'taxPercentage' => '15'], 'taxTotal'],
            'payment amount'           => [PaymentData::class, [], 'paymentAmount'],
            'reference payment amount' => [ReferenceData::class, [], 'paymentAmount'],
            'line price'               => [LineItemData::class, $line, 'price'],
            'line price extension'     => [LineItemData::class, $line, 'priceExtension'],
            'line net total'           => [LineItemData::class, $line, 'netTotal'],
            'price extension total'    => [TotalsData::class, $totals, 'priceExtensionTotalAmount'],
            'net total'                => [TotalsData::class, $totals, 'netTotalAmount'],
            'tax total amount'         => [TotalsData::class, $totals, 'taxTotalAmount'],
            'payable amount'           => [TotalsData::class, $totals, 'payableAmount'],
            'charge total'             => [TotalsData::class, $totals, 'chargeTotalAmount'],
            'discount total'           => [TotalsData::class, $totals, 'discountTotalAmount'],
            'withholding tax total'    => [TotalsData::class, $totals, 'withholdingTaxTotalAmount'],
            'payable rounding'         => [TotalsData::class, $totals, 'payableRoundingAmount'],
        ];
    }

    /**
     * @return array<string, array{Money, string}>
     */
    public static function invalidMoney(): array
    {
        return [
            'foreign currency'      => [FiscalMoney::of('1', 'EUR'), 'Money currency does not match the requested currency.'],
            'excess decimal places' => [Money::of('1.123456', 'CVE', new CustomContext(6)), 'Value exceeds the allowed decimal precision.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rentReceipt(): array
    {
        return [
            'assetId'         => 'HOUSE-1', 'rentPurposeTypeCode' => '2', 'contractTypeCode' => '1', 'rentTypeCode' => '1',
            'referencePeriod' => '2026-10', 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Example address'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function completeLineItem(): array
    {
        return [
            'quantity' => ['value' => '2', 'unitCode' => 'C62'],
            'price'    => '1.23456', 'priceExtension' => '2.46912', 'netTotal' => '2.46912',
            'id'       => 'line-1', 'lineReferenceId' => 'source-1', 'orderLineReference' => 99999,
            'item'     => [
                'description'            => 'Example item', 'emitterIdentification' => 'SKU-1', 'name' => 'Product',
                'brandName'              => 'Brand', 'modelName' => 'Model', 'hazardousRiskIndicator' => false,
                'packQuantity'           => ['value' => '6', 'unitCode' => 'C62'],
                'standardIdentification' => ['gtin' => '123456789'],
                'extraProperties'        => [['name' => 'Colour', 'value' => 'Blue']],
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function invalidTaxRepresentations(): array
    {
        return [
            'no representation' => [
                ['taxTypeCode' => TaxType::NotApplicable],
                'taxPercentage',
                'The tax percentage field is required when none of tax amount / tax exemption reason code are present.',
            ],
            'percentage and amount' => [
                ['taxTypeCode' => TaxType::ValueAddedTax, 'taxPercentage' => BigDecimal::of('15'), 'taxAmount' => FiscalMoney::cve('1')],
                'taxPercentage',
                'The tax percentage field prohibits tax amount / tax exemption reason code from being present.',
            ],
            'four decimal percentage' => [
                ['taxTypeCode' => TaxType::ValueAddedTax, 'taxPercentage' => BigDecimal::of('15.1234')],
                'taxPercentage',
                'Value exceeds the allowed decimal precision.',
            ],
            'stamp tax without code' => [
                ['taxTypeCode' => TaxType::StampTax, 'taxAmount' => FiscalMoney::cve('1')],
                'stampTaxCode',
                'The stamp tax code field is required.',
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function numericBypasses(): array
    {
        return [
            'negative quantity' => [
                QuantityData::class,
                ['value' => BigDecimal::of('-1'), 'unitCode' => 'C62'],
                'value',
                'The value is outside its permitted numeric bounds.',
            ],
            'null quantity' => [
                QuantityData::class,
                ['value' => null, 'unitCode' => 'C62'],
                'value',
                'The value field is required.',
            ],
            'foreign tax amount' => [
                TaxData::class,
                ['taxTypeCode' => TaxType::ValueAddedTax, 'taxAmount' => FiscalMoney::of('1', 'EUR')],
                'taxAmount',
                'Money currency does not match the requested currency.',
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function invalidDiscountsAndAlternateAmounts(): array
    {
        return [
            'money as a percentage discount' => [
                DiscountData::class,
                ['value' => FiscalMoney::cve('1')],
                'value',
                'Money currency does not match the requested currency.',
            ],
            'foreign amount discount' => [
                DiscountData::class,
                ['value' => FiscalMoney::of('1', 'EUR'), 'valueType' => DiscountValueType::Amount],
                'value',
                'Money currency does not match the requested currency.',
            ],
            'zero exchange rate' => [
                PayableAlternativeAmountData::class,
                ['value' => FiscalMoney::of('1', 'EUR'), 'currencyCode' => 'EUR', 'exchangeRate' => BigDecimal::of('0')],
                'exchangeRate',
                'The exchange rate is outside its permitted numeric bounds.',
            ],
            'six decimal exchange rate' => [
                PayableAlternativeAmountData::class,
                ['value' => FiscalMoney::of('1', 'EUR'), 'currencyCode' => 'EUR', 'exchangeRate' => BigDecimal::of('1.123456')],
                'exchangeRate',
                'Value exceeds the allowed decimal precision.',
            ],
            'uncatalogued currency' => [
                PayableAlternativeAmountData::class,
                ['value' => Money::of('1', 'IDR'), 'currencyCode' => 'IDR', 'exchangeRate' => BigDecimal::of('1')],
                'currencyCode',
                'The currency code must be a code in the official catalog.',
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function uncataloguedTaxCodes(): array
    {
        return [
            'exemption reason' => [
                'from',
                ['taxTypeCode' => TaxType::NotApplicable, 'taxExemptionReasonCode' => 'unknown'],
                'taxExemptionReasonCode',
                'The tax exemption reason code must be a code in the official catalog.',
            ],
            'stamp tax code' => [
                'validateAndCreate',
                ['taxTypeCode' => 'IS', 'taxAmount' => '1', 'stampTaxCode' => 10],
                'stampTaxCode',
                'The selected stamp tax code is invalid.',
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function invalidDurations(): array
    {
        return [
            'hour beyond the day' => [
                ['startDate' => '2026-01-01', 'startTime' => '24:01:00'],
                'startTime',
                'The start time must use a valid fiscal date or time.',
            ],
            'end before the start' => [
                ['startDate' => '2026-01-01', 'startTime' => '09:00:00', 'endDate' => '2026-01-01', 'endTime' => '08:59:59'],
                'endTime',
                'The end time field must be a date after or equal to startTime.',
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function castCoercions(): array
    {
        return [
            'integer delivery date' => [
                DeliveryData::class,
                ['deliveryDate' => 123, 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Example address']],
                'deliveryDate',
                'The delivery date must use a valid fiscal date or time.',
            ],
            'string amount without currency' => [
                PayableAlternativeAmountData::class,
                ['value' => '1', 'exchangeRate' => '1'],
                'currencyCode',
                'Currency must be an uppercase code of the official currency catalog.',
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function invalidContingencyExtensionAndSelfBilling(): array
    {
        return [
            'other reason without description' => [
                ContingencyData::class,
                ['issueDate' => '2026-10-02', 'reasonTypeCode' => '0', 'ledCode' => 1],
                'reasonDescription',
                'The reason description field is required.',
            ],
            'reserved extension name' => [
                ExtraFieldData::class,
                ['name' => 'PayableAmount', 'value' => '1'],
                'name',
                'The name is reserved for an official fiscal field.',
            ],
            'malformed authorization id' => [
                SelfBillingData::class,
                ['authorizationId' => 'invalid', 'authorizationCode' => '1234'],
                'authorizationId',
                'The authorization id field format is invalid.',
            ],
        ];
    }
}
