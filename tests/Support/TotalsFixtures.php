<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;

final class TotalsFixtures
{
    /**
     * @return list<LineItemData>
     */
    public static function informationalLines(string $normalNet = '100', string $informationalNet = '10'): array
    {
        return [
            F::line(['netTotal' => $normalNet]),
            F::line(['lineTypeCode' => 'D', 'price' => '20', 'priceExtension' => '20', 'netTotal' => '20']),
            F::line(['lineTypeCode' => 'I', 'price' => '10', 'priceExtension' => '10', 'netTotal' => $informationalNet]),
        ];
    }

    public static function informationalTaxTotals(): TotalsData
    {
        return F::totals([
            'priceExtensionTotalAmount' => '80',
            'netTotalAmount'            => '80',
            'discountTotalAmount'       => '20',
            'taxTotalAmount'            => '13.5',
            'payableAmount'             => '93.5',
        ]);
    }

    public static function amountDiscountWithInformationalTotals(): TotalsData
    {
        return F::totals([
            'priceExtensionTotalAmount' => '80',
            'netTotalAmount'            => '70',
            'taxTotalAmount'            => '12',
            'payableAmount'             => '82',
            'discount'                  => ['value' => '10', 'valueType' => 'A'],
        ]);
    }

    /**
     * @return list<LineItemData>
     */
    public static function taxAndWithholdingLines(): array
    {
        $line = F::line([
            'price'          => '0.05',
            'priceExtension' => '0.05',
            'netTotal'       => '0.05',
            'taxes'          => [['taxTypeCode' => 'IVA', 'taxPercentage' => '10'], ['taxTypeCode' => 'IR', 'taxPercentage' => '10']],
        ]);

        return [$line, $line];
    }

    public static function taxAndWithholdingTotals(string $tax): TotalsData
    {
        return F::totals([
            'priceExtensionTotalAmount' => '0.1',
            'netTotalAmount'            => '0.1',
            'taxTotalAmount'            => $tax,
            'withholdingTaxTotalAmount' => $tax,
            'payableAmount'             => '0.1',
        ]);
    }

    /**
     * @return list<LineItemData>
     */
    public static function percentageDiscountAndChargeLines(): array
    {
        return [
            F::line(['id' => 'A', 'discount' => ['value' => '10'], 'netTotal' => '81']),
            F::line(['lineTypeCode' => 'C', 'lineReferenceId' => 'A', 'price' => '10', 'priceExtension' => '10', 'netTotal' => '9']),
        ];
    }

    public static function percentageDiscountAndChargeTotals(): TotalsData
    {
        return F::totals([
            'priceExtensionTotalAmount' => '110',
            'netTotalAmount'            => '90',
            'chargeTotalAmount'         => '10',
            'discountTotalAmount'       => '10',
            'discount'                  => ['value' => '10'],
            'taxTotalAmount'            => '13.5',
            'payableRoundingAmount'     => '-0.005',
            'payableAmount'             => '103.495',
        ]);
    }

    /**
     * @return list<LineItemData>
     */
    public static function smallTaxLines(): array
    {
        $line = F::line(['price' => '0.07', 'priceExtension' => '0.07', 'netTotal' => '0.07', 'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '10']]]);

        return [$line, $line];
    }

    public static function smallTaxTotals(string $tax, string $payable): TotalsData
    {
        return F::totals(['priceExtensionTotalAmount' => '0.14', 'netTotalAmount' => '0.14', 'taxTotalAmount' => $tax, 'payableAmount' => $payable]);
    }

    public static function amountDiscountAllocationTotals(): TotalsData
    {
        return F::totals([
            'priceExtensionTotalAmount' => '200',
            'netTotalAmount'            => '190',
            'taxTotalAmount'            => '28.5',
            'payableAmount'             => '218.5',
            'discount'                  => ['value' => '10', 'valueType' => 'A'],
        ]);
    }

    /**
     * @return list<LineItemData>
     */
    public static function withheldLines(string $lineType, string $amount): array
    {
        return [
            F::line(['taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15'], ['taxTypeCode' => 'IR', 'taxPercentage' => '10']]]),
            F::line(['lineTypeCode' => $lineType, 'price' => $amount, 'priceExtension' => $amount, 'netTotal' => $amount,
                'taxes'             => [['taxTypeCode' => 'IR', 'taxPercentage' => '10']]]),
        ];
    }

    public static function withheldTotals(string $net, string $withholding, string $payable): TotalsData
    {
        return F::totals([
            'priceExtensionTotalAmount' => $net,
            'netTotalAmount'            => $net,
            'withholdingTaxTotalAmount' => $withholding,
            'payableAmount'             => $payable,
        ]);
    }

    /**
     * @return list<LineItemData>
     */
    public static function deductionBeyondNetLines(): array
    {
        return [
            F::line(['price' => '0.006', 'priceExtension' => '0.006', 'discount' => ['value' => '0.005', 'valueType' => 'A'], 'netTotal' => '0.001', 'taxes' => []]),
            F::line(['lineTypeCode' => 'D', 'price' => '0.005', 'priceExtension' => '0.005', 'netTotal' => '0.005', 'taxes' => []]),
        ];
    }

    public static function deductionBeyondNetTotals(): TotalsData
    {
        return F::totals(['priceExtensionTotalAmount' => '0.001', 'netTotalAmount' => '0', 'taxTotalAmount' => '0', 'payableAmount' => '0']);
    }
}
