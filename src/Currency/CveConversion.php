<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\DecimalFormatter;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

final readonly class CveConversion
{
    private const int CENTS = 2;

    private const int HUNDRED = 100;

    private const array AMOUNTS = ['price', 'priceExtension', 'netTotal', 'taxAmount', 'taxTotal', 'paymentAmount'];

    private const array SECTIONS = ['lines', 'references', 'payments'];

    public function __construct(private ExchangeRateQuote $quote) {}

    /**
     * @param  array<array-key, mixed> $payload
     * @return array<array-key, mixed>
     */
    public function payload(array $payload, HasTotals $document): array
    {
        foreach (self::SECTIONS as $section) {
            if (\is_array($payload[$section] ?? null)) {
                $payload[$section] = $this->converted($payload[$section]);
            }
        }

        return [...$payload, 'totals' => $this->totals($document)];
    }

    /**
     * @param  array<array-key, mixed> $node
     * @return array<array-key, mixed>
     */
    private function converted(array $node): array
    {
        foreach ($node as $key => $value) {
            if (\in_array($key, self::AMOUNTS, true)) {
                $node[$key] = $this->convertedValue($value);

                continue;
            }

            if ($key === 'discount' && \is_array($value) && ($value['valueType'] ?? null) === DiscountValueType::Amount->value) {
                $node[$key] = [...$value, 'value' => $this->convertedValue($value['value'] ?? null)];

                continue;
            }

            if (\is_array($value)) {
                $node[$key] = $this->converted($value);
            }
        }

        return $node;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function totals(HasTotals $document): array
    {
        $zero        = BigDecimal::zero();
        $sums        = ['priceExtension' => $zero, 'net' => $zero, 'charge' => $zero, 'discount' => $zero, 'tax' => $zero, 'withholding' => $zero];
        $withholding = false;

        foreach ($document->lines as $line) {
            $sums        = $this->line($sums, $line);
            $withholding = $withholding || collect($line->taxes)->contains(static fn (TaxData $tax): bool => $tax->taxType === TaxType::IncomeTax);
        }

        $totals   = $document->totals;
        $payable  = $this->amount($totals->payableAmount->getAmount());
        $rounding = $payable->minus($sums['net'])->minus($sums['tax'])->plus($sums['withholding']);

        return [
            ...$this->converted($totals->toPayload()),
            'priceExtensionTotalAmount' => (string) $this->round($sums['priceExtension']),
            'netTotalAmount'            => (string) $this->round($sums['net']),
            'taxTotalAmount'            => (string) $sums['tax'],
            'chargeTotalAmount'         => $totals->chargeTotalAmount instanceof Money ? (string) $this->round($sums['charge']) : null,
            'discountTotalAmount'       => $totals->discountTotalAmount instanceof Money ? (string) $this->round($sums['discount']) : null,
            'withholdingTaxTotalAmount' => $withholding ? (string) $sums['withholding'] : null,
            'payableRoundingAmount'     => ! $totals->payableRoundingAmount instanceof Money && $rounding->isZero() ? null : (string) $rounding,
            'payableAmount'             => (string) $payable,
            'payableAlternativeAmounts' => [[
                'value'        => (string) $totals->payableAmount->getAmount(),
                'currencyCode' => $this->quote->sourceCurrency,
                'exchangeRate' => (string) $this->quote->rate,
            ]],
        ];
    }

    /**
     * @param  array<string, BigDecimal> $sums
     * @return array<string, BigDecimal>
     */
    private function line(array $sums, LineItemData $line): array
    {
        $type           = $line->lineType;
        $sign           = $type->netSign();
        $priceExtension = $this->money($line->priceExtension);
        $net            = $this->money($line->netTotal);
        $discount       = $type->participatesInNetTotals() ? $this->discount($line->discount, $priceExtension) : BigDecimal::zero();

        $sums['priceExtension'] = $sums['priceExtension']->plus($priceExtension->multipliedBy($sign));
        $sums['net']            = $sums['net']->plus($net->multipliedBy($sign));
        $sums['charge']         = $sums['charge']->plus($type === LineType::Charge ? $priceExtension : 0);
        $sums['discount']       = $sums['discount']->plus($discount)->plus($type === LineType::Deduction ? $priceExtension : 0);

        foreach ($line->taxes as $tax) {
            $amount = $this->taxAmount($tax, $net);

            if ($tax->taxType === TaxType::IncomeTax) {
                $sums['withholding'] = $sums['withholding']->plus($amount->multipliedBy($sign));

                continue;
            }

            $sums['tax'] = $sums['tax']->plus($amount->multipliedBy($type === LineType::Deduction ? -1 : 1));
        }

        return $sums;
    }

    private function taxAmount(TaxData $tax, BigDecimal $net): BigDecimal
    {
        if ($tax->taxPercentage instanceof BigDecimal) {
            return $this->round($net->multipliedBy($tax->taxPercentage)->dividedByExact(self::HUNDRED));
        }

        return $this->money($tax->taxAmount);
    }

    private function convertedValue(mixed $value): mixed
    {
        return \is_string($value) ? (string) $this->amount(BigDecimal::of($value)) : $value;
    }

    private function discount(?DiscountData $discount, BigDecimal $base): BigDecimal
    {
        if (! $discount instanceof DiscountData) {
            return BigDecimal::zero();
        }

        return $discount->value instanceof Money ? $this->money($discount->value) : $base->multipliedBy($discount->value)->dividedByExact(self::HUNDRED);
    }

    private function money(?Money $amount): BigDecimal
    {
        return $this->amount($amount?->getAmount() ?? BigDecimal::zero());
    }

    private function amount(BigDecimal $amount): BigDecimal
    {
        return $this->round($amount->multipliedBy($this->quote->rate));
    }

    private function round(BigDecimal $amount): BigDecimal
    {
        return $amount->toScale(self::CENTS, DecimalFormatter::fiscalRounding());
    }
}
