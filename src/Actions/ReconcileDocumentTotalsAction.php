<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\DecimalFormatter;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;

final readonly class ReconcileDocumentTotalsAction
{
    /**
     * @param list<LineItemData> $lines
     */
    public function handle(array $lines, TotalsData $totals): TotalsData
    {
        $extension          = BigDecimal::zero();
        $net                = $extension;
        $charge             = $extension;
        $discount           = $extension;
        $allocated          = $extension;
        $tax                = $extension;
        $roundedTax         = $extension;
        $withholding        = $extension;
        $roundedWithholding = $extension;
        foreach ($lines as $index => $line) {
            if ($line->price === null || $line->priceExtension === null || $line->netTotal === null) {
                $this->fail('lines.' . $index);
            }

            $priceExtension = $line->priceExtension->getAmount();
            $netTotal       = $line->netTotal->getAmount();
            $this->matches('lines.' . $index . '.priceExtension', $priceExtension, [$line->price->getAmount()->multipliedBy($line->quantity->value)], true);
            $lineDiscount   = $this->discount($line->discount, $priceExtension);
            $base           = $priceExtension->minus($lineDiscount);
            $amountDiscount = $totals->discount?->valueType === DiscountValueType::Amount;
            if ($amountDiscount && \in_array($line->lineTypeCode, [LineType::Normal, LineType::Charge], true)) {
                $allocation = $base->minus($netTotal);
                if ($allocation->isNegative()) {
                    $this->fail('lines.' . $index . '.netTotal');
                }

                $allocated = $allocated->plus($allocation);
            } else {
                $globalDiscount = $line->lineTypeCode === LineType::Deduction || $amountDiscount ? BigDecimal::zero() : $this->discount($totals->discount, $base);
                $this->matches('lines.' . $index . '.netTotal', $netTotal, [$base->minus($globalDiscount)], true);
            }

            $sign      = $line->lineTypeCode->netSign();
            $extension = $extension->plus($priceExtension->multipliedBy($sign));
            $net       = $net->plus($netTotal->multipliedBy($sign));
            if ($line->lineTypeCode === LineType::Charge) {
                $charge = $charge->plus($priceExtension);
            }

            if ($line->lineTypeCode !== LineType::Information) {
                $discount = $discount->plus($lineDiscount);
            }

            if ($line->lineTypeCode === LineType::Deduction) {
                $discount = $discount->plus($priceExtension);
            }

            foreach ($line->taxes as $itemTax) {
                $amount = $itemTax->taxPercentage !== null ? $netTotal->multipliedBy($itemTax->taxPercentage)->dividedBy('100') : ($itemTax->taxAmount?->getAmount() ?? BigDecimal::zero());
                if ($itemTax->taxTotal !== null) {
                    $this->matches('lines.' . $index . '.taxTotal', $itemTax->taxTotal->getAmount(), [$amount], true);
                }

                if ($itemTax->taxTypeCode === TaxType::IncomeTax) {
                    $withholding        = $withholding->plus($amount->multipliedBy($sign));
                    $roundedWithholding = $roundedWithholding->plus($this->round($amount)->multipliedBy($sign));
                } else {
                    $taxSign    = $line->lineTypeCode === LineType::Deduction ? -1 : 1;
                    $tax        = $tax->plus($amount->multipliedBy($taxSign));
                    $roundedTax = $roundedTax->plus($this->round($amount)->multipliedBy($taxSign));
                }
            }
        }

        if ($totals->discount?->valueType === DiscountValueType::Amount) {
            $this->matches('totals.discount', $this->discount($totals->discount, BigDecimal::zero()), [$allocated]);
        }

        $this->matches('totals.priceExtensionTotalAmount', $totals->priceExtensionTotalAmount->getAmount(), [$extension], true);
        $this->matches('totals.netTotalAmount', $totals->netTotalAmount->getAmount(), [$net], true);
        $this->matches('totals.taxTotalAmount', $totals->taxTotalAmount->getAmount(), [$tax, $roundedTax], true);
        foreach (['chargeTotalAmount' => $charge, 'discountTotalAmount' => $discount] as $field => $expected) {
            if ($totals->{$field} instanceof Money) {
                $this->matches('totals.' . $field, $totals->{$field}->getAmount(), [$expected], true);
            }
        }

        if ($totals->withholdingTaxTotalAmount instanceof Money) {
            $this->matches('totals.withholdingTaxTotalAmount', $totals->withholdingTaxTotalAmount->getAmount(), [$withholding, $roundedWithholding], true);
        } elseif (! $withholding->isZero()) {
            $this->fail('totals.withholdingTaxTotalAmount');
        }

        $payable = $totals->netTotalAmount->getAmount()->plus($totals->taxTotalAmount->getAmount())->plus($totals->payableRoundingAmount?->getAmount() ?? '0');
        $this->matches('totals.payableAmount', $totals->payableAmount->getAmount(), [$payable]);

        return $totals;
    }

    private function discount(?DiscountData $discount, BigDecimal $base): BigDecimal
    {
        if (! $discount instanceof DiscountData) {
            return BigDecimal::zero();
        }

        $value = $discount->value instanceof Money ? $discount->value->getAmount() : $discount->value;

        return $discount->valueType === DiscountValueType::Amount ? $value : $base->multipliedBy($value)->dividedBy('100');
    }

    private function round(BigDecimal $value): BigDecimal
    {
        return $value->toScale(2, DecimalFormatter::roundingMode(true));
    }

    /**
     * @param list<BigDecimal> $candidates
     */
    private function matches(string $field, BigDecimal $actual, array $candidates, bool $rounded = false): void
    {
        foreach ($candidates as $candidate) {
            if (! $candidate->isNegative() && ($actual->isEqualTo($candidate) || ($rounded && $actual->isEqualTo($this->round($candidate))))) {
                return;
            }
        }

        $this->fail($field);
    }

    private function fail(string $field): never
    {
        throw ValidationException::withMessages([$field => __('efatura::efatura.validation.reconciliation')]);
    }
}
