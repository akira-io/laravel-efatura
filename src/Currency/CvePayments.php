<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Arr;

final readonly class CvePayments
{
    private const string CENT = '0.01';

    public function __construct(private BigDecimal $rate) {}

    /**
     * @param  array<array-key, mixed> $source
     * @param  array<array-key, mixed> $converted
     * @return array<array-key, mixed>
     */
    public function balanced(array $source, array $converted, BigDecimal $sourcePayable, BigDecimal $payable): array
    {
        $amounts = $this->amounts(Arr::get($source, 'payments.payments'));

        if ($amounts === [] || ! BigDecimal::sum(BigDecimal::zero(), ...$amounts)->isEqualTo($sourcePayable)) {
            return $converted;
        }

        foreach ($this->shares($amounts, $payable) as $index => $share) {
            Arr::set($converted, 'payments.payments.' . $index . '.paymentAmount', (string) $share);
        }

        return $converted;
    }

    /**
     * @return array<array-key, BigDecimal>
     */
    private function amounts(mixed $payments): array
    {
        $amounts = [];
        foreach (\is_array($payments) ? $payments : [] as $index => $payment) {
            $amount = \is_array($payment) ? $payment['paymentAmount'] ?? null : null;

            if (\is_string($amount)) {
                $amounts[$index] = BigDecimal::of($amount);
            }
        }

        return $amounts;
    }

    /**
     * @param  array<array-key, BigDecimal> $amounts
     * @return array<array-key, BigDecimal>
     */
    private function shares(array $amounts, BigDecimal $payable): array
    {
        $exact   = array_map(fn (BigDecimal $amount): BigDecimal => $amount->multipliedBy($this->rate), $amounts);
        $shares  = array_map(static fn (BigDecimal $amount): BigDecimal => $amount->toScale(2, RoundingMode::Down), $exact);
        $missing = $payable->minus(BigDecimal::sum(BigDecimal::zero(), ...array_values($shares)))->dividedBy(self::CENT, 0)->toInt();
        $order   = collect($exact)
            ->map(static fn (BigDecimal $amount, int|string $index): BigDecimal => $amount->minus($shares[$index]))
            ->sort(static fn (BigDecimal $left, BigDecimal $right): int => $right->compareTo($left))
            ->keys()
            ->take($missing);

        foreach ($order as $index) {
            $shares[$index] = $shares[$index]->plus(self::CENT);
        }

        return $shares;
    }
}
