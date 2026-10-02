<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Money\TotalsAccumulator;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;

final readonly class VerifyDocumentTotalsAction
{
    private const int HUNDRED = 100;

    private const int ROUNDED_SCALE = 2;

    private const array ACCEPTED_SCALES = [self::ROUNDED_SCALE, Fiscal::AMOUNT_SCALE];

    /**
     * @param list<LineItemData> $lines
     */
    public function handle(array $lines, TotalsData $totals): void
    {
        $amountDiscount = $totals->discount?->valueType === DiscountValueType::Amount;
        $sum            = collect($lines)->reduce(
            fn (TotalsAccumulator $carry, LineItemData $line, int $index): TotalsAccumulator => $this->line($carry, $line, $index, $totals->discount, $amountDiscount),
            TotalsAccumulator::zero(),
        );

        if ($amountDiscount) {
            $this->matchesExact('totals.discount', $this->discount($totals->discount, BigDecimal::zero()), [$sum->allocatedDiscount]);
        }

        $this->matchesRounded('totals.priceExtensionTotalAmount', $totals->priceExtensionTotalAmount->getAmount(), [$sum->priceExtension]);
        $this->matchesRounded('totals.netTotalAmount', $totals->netTotalAmount->getAmount(), [$sum->net]);
        $this->matchesRounded('totals.taxTotalAmount', $totals->taxTotalAmount->getAmount(), [$sum->tax, $sum->roundedTax]);

        if ($totals->chargeTotalAmount instanceof Money) {
            $this->matchesRounded('totals.chargeTotalAmount', $totals->chargeTotalAmount->getAmount(), [$sum->charge]);
        }

        if ($totals->discountTotalAmount instanceof Money) {
            $this->matchesRounded('totals.discountTotalAmount', $totals->discountTotalAmount->getAmount(), [$sum->discount]);
        }

        if ($totals->withholdingTaxTotalAmount instanceof Money) {
            $this->matchesRounded('totals.withholdingTaxTotalAmount', $totals->withholdingTaxTotalAmount->getAmount(), [$sum->withholding, $sum->roundedWithholding]);
        }

        if (! $totals->withholdingTaxTotalAmount instanceof Money && ! $sum->withholding->isZero()) {
            $this->fail('totals.withholdingTaxTotalAmount');
        }

        $payable = $totals->netTotalAmount->getAmount()
            ->plus($totals->taxTotalAmount->getAmount())
            ->plus($totals->payableRoundingAmount?->getAmount() ?? BigDecimal::zero());
        $this->matchesExact('totals.payableAmount', $totals->payableAmount->getAmount(), [$payable]);
    }

    private function line(TotalsAccumulator $sum, LineItemData $line, int $index, ?DiscountData $globalDiscount, bool $amountDiscount): TotalsAccumulator
    {
        if (! $line->price instanceof Money || ! $line->priceExtension instanceof Money || ! $line->netTotal instanceof Money) {
            $this->fail('lines.' . $index);
        }

        $type           = $line->lineTypeCode;
        $priceExtension = $line->priceExtension->getAmount();
        $net            = $line->netTotal->getAmount();
        $this->matchesRounded('lines.' . $index . '.priceExtension', $priceExtension, [$line->price->getAmount()->multipliedBy($line->quantity->value)]);

        $lineDiscount = $this->discount($line->discount, $priceExtension);
        $base         = $priceExtension->minus($lineDiscount);
        $allocated    = $this->allocatedDiscount($index, $type, $base, $net, $globalDiscount, $amountDiscount);
        $sign         = $type->netSign();
        $zero         = BigDecimal::zero();

        $sum = $sum->addLine(
            priceExtension: $priceExtension->multipliedBy($sign),
            net: $net->multipliedBy($sign),
            charge: $type === LineType::Charge ? $priceExtension : $zero,
            discount: ($type->participatesInNetTotals() ? $lineDiscount : $zero)->plus($type === LineType::Deduction ? $priceExtension : $zero),
            allocatedDiscount: $allocated,
        );

        return collect($line->taxes)->reduce(
            fn (TotalsAccumulator $carry, TaxData $tax): TotalsAccumulator => $this->tax($carry, $tax, $index, $type, $net),
            $sum,
        );
    }

    private function allocatedDiscount(int $index, LineType $type, BigDecimal $base, BigDecimal $net, ?DiscountData $globalDiscount, bool $amountDiscount): BigDecimal
    {
        if ($amountDiscount && \in_array($type, [LineType::Normal, LineType::Charge], true)) {
            $allocation = $base->minus($net);
            if ($allocation->isNegative()) {
                $this->fail('lines.' . $index . '.netTotal');
            }

            return $allocation;
        }

        $discount = $type === LineType::Deduction || $amountDiscount ? BigDecimal::zero() : $this->discount($globalDiscount, $base);
        $this->matchesRounded('lines.' . $index . '.netTotal', $net, [$base->minus($discount)]);

        return BigDecimal::zero();
    }

    private function tax(TotalsAccumulator $sum, TaxData $tax, int $index, LineType $type, BigDecimal $net): TotalsAccumulator
    {
        $amount = $tax->taxPercentage instanceof BigDecimal
            ? $net->multipliedBy($tax->taxPercentage)->dividedByExact(self::HUNDRED)
            : ($tax->taxAmount?->getAmount() ?? BigDecimal::zero());

        if ($tax->taxTotal instanceof Money) {
            $this->matchesRounded('lines.' . $index . '.taxTotal', $tax->taxTotal->getAmount(), [$amount]);
        }

        if ($tax->taxTypeCode === TaxType::IncomeTax) {
            $sign = $type->netSign();

            return $sum->addWithholding($amount->multipliedBy($sign), $this->round($amount)->multipliedBy($sign));
        }

        $sign = $type === LineType::Deduction ? -1 : 1;

        return $sum->addTax($amount->multipliedBy($sign), $this->round($amount)->multipliedBy($sign));
    }

    private function discount(?DiscountData $discount, BigDecimal $base): BigDecimal
    {
        if (! $discount instanceof DiscountData) {
            return BigDecimal::zero();
        }

        $value = $discount->value instanceof Money ? $discount->value->getAmount() : $discount->value;

        return $discount->valueType === DiscountValueType::Amount ? $value : $base->multipliedBy($value)->dividedByExact(self::HUNDRED);
    }

    private function round(BigDecimal $value): BigDecimal
    {
        return $value->toScale(self::ROUNDED_SCALE, DecimalFormatter::fiscalRounding());
    }

    /**
     * @param list<BigDecimal> $candidates
     */
    private function matchesExact(string $field, BigDecimal $actual, array $candidates): void
    {
        foreach ($candidates as $candidate) {
            if (! $candidate->isNegative() && $actual->isEqualTo($candidate)) {
                return;
            }
        }

        $this->fail($field);
    }

    /**
     * @param list<BigDecimal> $candidates
     */
    private function matchesRounded(string $field, BigDecimal $actual, array $candidates): void
    {
        foreach ($candidates as $candidate) {
            if (! $candidate->isNegative() && $this->roundsTo($candidate, $actual)) {
                return;
            }
        }

        $this->fail($field);
    }

    private function roundsTo(BigDecimal $candidate, BigDecimal $actual): bool
    {
        return $actual->isEqualTo($candidate) || collect(self::ACCEPTED_SCALES)->contains(
            static fn (int $scale): bool => $actual->isEqualTo($candidate->toScale($scale, DecimalFormatter::fiscalRounding())),
        );
    }

    private function fail(string $field): never
    {
        throw ValidationException::withMessages([$field => __('efatura::efatura.validation.reconciliation')]);
    }
}
