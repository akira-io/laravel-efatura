<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Data\Contracts\HasTotals;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Money\DecimalFormatter;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

final readonly class CveConversion
{
    private const array AMOUNTS = ['taxAmount', 'taxTotal', 'paymentAmount'];

    private const array SECTIONS = ['references', 'payments'];

    private CveLines $lines;

    public function __construct(private ExchangeRateQuote $quote)
    {
        $this->lines = new CveLines($quote->rate);
    }

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

        $lines = $this->lines->convert($document->lines, $document->totals->discount);

        return [
            ...$payload,
            'lines'  => array_map(static fn (ConvertedLine $line): array => $line->payload, $lines),
            'totals' => $this->totals($document, $lines),
        ];
    }

    /**
     * @param  array<array-key, mixed> $node
     * @return array<array-key, mixed>
     */
    private function converted(array $node): array
    {
        foreach ($node as $key => $value) {
            if (\in_array($key, self::AMOUNTS, true) && \is_string($value)) {
                $node[$key] = (string) $this->lines->amount(BigDecimal::of($value));

                continue;
            }

            if (\is_array($value)) {
                $node[$key] = $this->converted($value);
            }
        }

        return $node;
    }

    /**
     * @param  list<ConvertedLine>     $lines
     * @return array<array-key, mixed>
     */
    private function totals(HasTotals $document, array $lines): array
    {
        $zero = BigDecimal::zero();
        $sums = ['priceExtension' => $zero, 'net' => $zero, 'charge' => $zero, 'discount' => $zero, 'tax' => $zero, 'withholding' => $zero];

        foreach ($lines as $line) {
            $sign = $line->type->netSign();
            $sums = [
                'priceExtension' => $sums['priceExtension']->plus($line->priceExtension->multipliedBy($sign)),
                'net'            => $sums['net']->plus($line->net->multipliedBy($sign)),
                'charge'         => $sums['charge']->plus($line->type === LineType::Charge ? $line->priceExtension : 0),
                'discount'       => $sums['discount']->plus($line->discount),
                'tax'            => $sums['tax']->plus($line->tax),
                'withholding'    => $sums['withholding']->plus($line->withholding),
            ];
        }

        $totals   = $document->totals;
        $net      = self::round($sums['net']);
        $payable  = $this->lines->amount($totals->payableAmount->getAmount());
        $rounding = $payable->minus($net)->minus($sums['tax'])->plus($sums['withholding']);

        return [
            ...$totals->toPayload(),
            'priceExtensionTotalAmount' => (string) self::round($sums['priceExtension']),
            'netTotalAmount'            => (string) $net,
            'taxTotalAmount'            => (string) $sums['tax'],
            'chargeTotalAmount'         => $totals->chargeTotalAmount instanceof Money ? (string) self::round($sums['charge']) : null,
            'discountTotalAmount'       => $totals->discountTotalAmount instanceof Money ? (string) self::round($sums['discount']) : null,
            'withholdingTaxTotalAmount' => collect($lines)->contains('withholds', true) ? (string) $sums['withholding'] : null,
            'payableRoundingAmount'     => ! $totals->payableRoundingAmount instanceof Money && $rounding->isZero() ? null : (string) $rounding,
            'payableAmount'             => (string) $payable,
            'discount'                  => $this->lines->convertedDiscount($totals->discount)?->toPayload(),
            'payableAlternativeAmounts' => [[
                'value'        => (string) $totals->payableAmount->getAmount(),
                'currencyCode' => $this->quote->sourceCurrency,
                'exchangeRate' => (string) $this->quote->rate,
            ]],
        ];
    }

    private static function round(BigDecimal $amount): BigDecimal
    {
        return $amount->toScale(2, DecimalFormatter::fiscalRounding());
    }
}
