<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Random\Randomizer;

final class ForeignDocuments
{
    public const array RATES = ['0.00917', '0.5', '0.59727', '1', '3.33333', '110.265', '1234.56789'];

    private const array QUANTITIES = ['1', '2', '3', '0.5', '0.333', '7.25', '12', '100'];

    private const array PERCENTAGES = ['15', '8.5', '2.375', '23', '0.5'];

    private const array LINE_PERCENTAGES = ['5', '12.5', '33.333'];

    /**
     * @param list<array{type?: string, quantity?: string, price: string, discount?: array{value: string, valueType: string}, allocation?: string, tax?: string, incomeTax?: string}> $lines
     * @param array{value: string, valueType: string}|null                                                                                                                            $discount
     */
    public static function invoice(array $lines, ?array $discount = null): array
    {
        $zero = BigDecimal::zero();
        $sums = ['priceExtension' => $zero, 'net' => $zero, 'tax' => $zero, 'withholding' => $zero, 'discount' => $zero, 'allocated' => $zero];
        $rows = [];

        foreach ($lines as $line) {
            $type           = $line['type'] ?? 'N';
            $sign           = ['N' => 1, 'D' => -1, 'I' => 0][$type];
            $quantity       = $line['quantity'] ?? '1';
            $priceExtension = self::round(BigDecimal::of($line['price'])->multipliedBy($quantity));
            $lineDiscount   = self::discount($line['discount'] ?? null, $priceExtension);
            $base           = $priceExtension->minus($lineDiscount);
            $net            = self::net($type, $base, $line['allocation'] ?? null, $discount);
            $taxes          = [];

            if (isset($line['tax'])) {
                $amount      = self::round($net->multipliedBy($line['tax'])->dividedByExact(100));
                $taxes[]     = ['taxTypeCode' => 'IVA', 'taxPercentage' => $line['tax'], ...($amount->isZero() ? [] : ['taxTotal' => (string) $amount])];
                $sums['tax'] = $sums['tax']->plus($amount->multipliedBy($type === 'D' ? -1 : 1));
            }

            if (isset($line['incomeTax'])) {
                $taxes[]             = ['taxTypeCode' => 'IR', 'taxAmount' => $line['incomeTax'], 'taxTotal' => $line['incomeTax']];
                $sums['withholding'] = $sums['withholding']->plus(BigDecimal::of($line['incomeTax'])->multipliedBy($sign));
            }

            $sums['priceExtension'] = $sums['priceExtension']->plus($priceExtension->multipliedBy($sign));
            $sums['net']            = $sums['net']->plus($net->multipliedBy($sign));
            $sums['discount']       = $sums['discount']->plus($type === 'I' ? 0 : $lineDiscount)->plus($type === 'D' ? $priceExtension : 0);
            $sums['allocated']      = $sums['allocated']->plus($base->minus($net)->multipliedBy($type === 'N' ? 1 : 0));
            $rows[]                 = DocumentFixtures::linePayload([
                'lineTypeCode'   => $type,
                'quantity'       => ['value' => $quantity, 'unitCode' => 'C62'],
                'price'          => $line['price'],
                'priceExtension' => (string) $priceExtension,
                'netTotal'       => (string) $net,
                'discount'       => $line['discount'] ?? null,
                'taxes'          => $taxes,
            ]);
        }

        $withholds = collect($lines)->contains(static fn (array $line): bool => isset($line['incomeTax']));

        return DocumentFixtures::payload(['lines' => $rows, 'totals' => [
            'priceExtensionTotalAmount' => (string) self::round($sums['priceExtension']),
            'netTotalAmount'            => (string) self::round($sums['net']),
            'taxTotalAmount'            => (string) $sums['tax'],
            'discountTotalAmount'       => (string) self::round($sums['discount']),
            'withholdingTaxTotalAmount' => $withholds ? (string) $sums['withholding'] : null,
            'payableAmount'             => (string) self::round($sums['net'])->plus($sums['tax'])->minus($sums['withholding']),
            'discount'                  => ($discount['valueType'] ?? null) === 'A' ? ['value' => (string) $sums['allocated'], 'valueType' => 'A'] : $discount,
        ]]);
    }

    /**
     * @return array{array<string, mixed>, string}
     */
    public static function random(Randomizer $random): array
    {
        $rate    = self::RATES[$random->getInt(0, \count(self::RATES) - 1)];
        $global  = [null, ['value' => '7.5', 'valueType' => 'P'], ['value' => '0', 'valueType' => 'A']][$random->getInt(0, 2)];
        $amounts = ($global['valueType'] ?? null) === 'A';
        $lines   = [];
        $count   = $random->getInt(1, 4);

        for ($index = 0; $index < $count; $index++) {
            $type     = $index === 0 ? 'N' : ['N', 'N', 'D', 'I'][$random->getInt(0, 3)];
            $price    = (string) self::price($random, $type, $index);
            $quantity = $type === 'D' ? '1' : self::QUANTITIES[$random->getInt(0, \count(self::QUANTITIES) - 1)];
            $quantity = BigDecimal::of($price)->getScale() > 2 && $random->getInt(0, 1) === 0 ? '10000' : $quantity;
            $line     = ['type' => $type, 'quantity' => $quantity, 'price' => $price, 'tax' => $type === 'D' ? '0.5' : self::PERCENTAGES[$random->getInt(0, \count(self::PERCENTAGES) - 1)]];
            $extended = self::round(BigDecimal::of($price)->multipliedBy($quantity));

            if ($type === 'N' && $random->getInt(0, 2) === 0) {
                $line['discount'] = ['value' => (string) self::round($extended->multipliedBy($random->getInt(0, 20))->dividedByExact(100)), 'valueType' => 'A'];
            }

            if ($type === 'N' && ! $amounts && $random->getInt(0, 2) === 1) {
                $line['discount'] = ['value' => self::LINE_PERCENTAGES[$random->getInt(0, 2)], 'valueType' => 'P'];
            }

            if ($type === 'N' && $amounts) {
                $base               = $extended->minus(self::discount($line['discount'] ?? null, $extended));
                $line['allocation'] = (string) self::round($base->multipliedBy($random->getInt(0, 30))->dividedByExact(100));
            }

            $incomeTax = self::round($extended->dividedBy(10, 2, RoundingMode::Down));
            if ($type === 'N' && $random->getInt(0, 3) === 0 && self::round($incomeTax->multipliedBy($rate))->isPositive()) {
                $line['incomeTax'] = (string) $incomeTax;
            }

            $lines[] = $line;
        }

        return [self::invoice($lines, $global), $rate];
    }

    private static function price(Randomizer $random, string $type, int $index): BigDecimal
    {
        if ($type === 'D') {
            return BigDecimal::ofUnscaledValue($random->getInt(1, 100), 2);
        }

        return $index > 0 && $random->getInt(0, 2) === 0 ? BigDecimal::ofUnscaledValue($random->getInt(1, 999), 5) : BigDecimal::ofUnscaledValue($random->getInt(10_000, 99_999), 2);
    }

    private static function net(string $type, BigDecimal $base, ?string $allocation, ?array $discount): BigDecimal
    {
        if ($allocation !== null && $type === 'N') {
            return $base->minus($allocation);
        }

        $global = $type === 'D' || ($discount['valueType'] ?? null) !== 'P' ? BigDecimal::zero() : $base->multipliedBy($discount['value'])->dividedByExact(100);

        return self::round($base->minus($global));
    }

    private static function discount(?array $discount, BigDecimal $priceExtension): BigDecimal
    {
        return match ($discount['valueType'] ?? null) {
            'A'     => BigDecimal::of($discount['value']),
            'P'     => $priceExtension->multipliedBy($discount['value'])->dividedByExact(100),
            default => BigDecimal::zero(),
        };
    }

    private static function round(BigDecimal $value): BigDecimal
    {
        return $value->toScale(2, RoundingMode::HalfUp);
    }
}
