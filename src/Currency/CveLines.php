<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\TaxData;
use Akira\Efatura\Enums\DiscountValueType;
use Akira\Efatura\Enums\LineType;
use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

final readonly class CveLines
{
    private const int CENTS = 2;

    private const int HUNDRED = 100;

    private const string CENT = '0.01';

    public function __construct(private BigDecimal $rate) {}

    /**
     * @param  list<LineItemData>  $lines
     * @return list<ConvertedLine>
     */
    public function convert(array $lines, ?DiscountData $global): array
    {
        $allocations = $global?->value instanceof Money ? $this->allocations($lines, $this->amount($global->value->getAmount())) : [];
        $converted   = [];
        foreach ($lines as $index => $line) {
            $converted[] = $this->line($line, $global, $allocations[$index] ?? null);
        }

        return $converted;
    }

    public function amount(BigDecimal $amount): BigDecimal
    {
        return $this->round($amount->multipliedBy($this->rate));
    }

    public function convertedDiscount(?DiscountData $discount): ?DiscountData
    {
        if (! $discount?->value instanceof Money) {
            return $discount;
        }

        return DiscountData::from(['value' => (string) $this->amount($discount->value->getAmount()), 'valueType' => DiscountValueType::Amount->value]);
    }

    private function line(LineItemData $line, ?DiscountData $global, ?BigDecimal $allocation): ConvertedLine
    {
        $type                                       = $line->lineType;
        [$price, $priceExtension, $discount, $base] = $this->prices($line);
        $net                                        = $allocation instanceof BigDecimal
            ? $base->minus($allocation)
            : $this->round($base->minus($this->globalDiscount($type, $global, $base)));
        [$taxes, $tax, $withholding] = $this->taxes($line, $net);
        $lineDiscount                = $type->participatesInNetTotals() ? $priceExtension->minus($base) : BigDecimal::zero();

        return new ConvertedLine(
            $type,
            $priceExtension,
            $net,
            $lineDiscount->plus($type === LineType::Deduction ? $priceExtension : 0),
            $tax,
            $withholding,
            collect($line->taxes)->contains(static fn (TaxData $source): bool => $source->taxType === TaxType::IncomeTax),
            [
                ...$line->toPayload(),
                'price'          => (string) $price,
                'priceExtension' => (string) $priceExtension,
                'netTotal'       => (string) $net,
                'discount'       => $discount?->toPayload(),
                'taxes'          => $taxes,
            ],
        );
    }

    /**
     * @return array{BigDecimal, BigDecimal, ?DiscountData, BigDecimal}
     */
    private function prices(LineItemData $line): array
    {
        $price          = $this->unitPrice($line->price);
        $priceExtension = $this->round($price->multipliedBy($line->quantity->value));
        $discount       = $this->convertedDiscount($line->discount);

        return [$price, $priceExtension, $discount, $priceExtension->minus($this->discount($discount, $priceExtension))];
    }

    /**
     * @return array{list<array<array-key, mixed>>, BigDecimal, BigDecimal}
     */
    private function taxes(LineItemData $line, BigDecimal $net): array
    {
        $type        = $line->lineType;
        $payloads    = [];
        $tax         = BigDecimal::zero();
        $withholding = BigDecimal::zero();

        foreach ($line->taxes as $source) {
            $amount     = $this->taxAmount($source, $net);
            $payloads[] = [
                ...$source->toPayload(),
                'taxAmount' => $source->taxAmount instanceof Money ? (string) $amount : null,
                'taxTotal'  => $source->taxTotal instanceof Money && ! $amount->isZero() ? (string) $amount : null,
            ];

            if ($source->taxType === TaxType::IncomeTax) {
                $withholding = $withholding->plus($amount->multipliedBy($type->netSign()));

                continue;
            }

            $tax = $tax->plus($amount->multipliedBy($type === LineType::Deduction ? -1 : 1));
        }

        return [$payloads, $tax, $withholding];
    }

    /**
     * @param  list<LineItemData>     $lines
     * @return array<int, BigDecimal>
     */
    private function allocations(array $lines, BigDecimal $target): array
    {
        $allocations = [];
        $room        = [];
        foreach ($lines as $index => $line) {
            if (\in_array($line->lineType, [LineType::Normal, LineType::Charge], true)) {
                $priceExtension      = $line->priceExtension?->getAmount() ?? BigDecimal::zero();
                $base                = $priceExtension->minus($this->discount($line->discount, $priceExtension));
                $allocations[$index] = $this->amount($base->minus($line->netTotal?->getAmount() ?? BigDecimal::zero()));
                $room[$index]        = $this->prices($line)[3]->minus($allocations[$index]);
            }
        }

        $residual = $target->minus(BigDecimal::sum(BigDecimal::zero(), ...array_values($allocations)));
        $cent     = BigDecimal::of(self::CENT)->multipliedBy($residual->getSign());

        while (! $residual->isZero() && $allocations !== []) {
            $index               = self::largest($cent->isPositive() ? $room : $allocations);
            $allocations[$index] = $allocations[$index]->plus($cent);
            $room[$index]        = $room[$index]->minus($cent);
            $residual            = $residual->minus($cent);
        }

        return $allocations;
    }

    /**
     * @param array<int, BigDecimal> $values
     */
    private static function largest(array $values): int
    {
        $largest = (int) array_key_first($values);
        foreach ($values as $index => $value) {
            if ($value->isGreaterThan($values[$largest])) {
                $largest = $index;
            }
        }

        return $largest;
    }

    private function taxAmount(TaxData $tax, BigDecimal $net): BigDecimal
    {
        if ($tax->taxPercentage instanceof BigDecimal) {
            return $this->round($net->multipliedBy($tax->taxPercentage)->dividedByExact(self::HUNDRED));
        }

        return $this->money($tax->taxAmount);
    }

    private function globalDiscount(LineType $type, ?DiscountData $global, BigDecimal $base): BigDecimal
    {
        return $type === LineType::Deduction || $global?->valueType !== DiscountValueType::Percentage ? BigDecimal::zero() : $this->discount($global, $base);
    }

    private function discount(?DiscountData $discount, BigDecimal $base): BigDecimal
    {
        if (! $discount instanceof DiscountData) {
            return BigDecimal::zero();
        }

        return $discount->value instanceof Money ? $discount->value->getAmount() : $base->multipliedBy($discount->value)->dividedByExact(self::HUNDRED);
    }

    private function unitPrice(?Money $price): BigDecimal
    {
        return ($price?->getAmount() ?? BigDecimal::zero())->multipliedBy($this->rate)->toScale(Fiscal::AMOUNT_SCALE, DecimalFormatter::fiscalRounding());
    }

    private function money(?Money $amount): BigDecimal
    {
        return $this->amount($amount?->getAmount() ?? BigDecimal::zero());
    }

    private function round(BigDecimal $amount): BigDecimal
    {
        return $amount->toScale(self::CENTS, DecimalFormatter::fiscalRounding());
    }
}
