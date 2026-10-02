<?php

declare(strict_types=1);

namespace Akira\Efatura\Money;

use Brick\Math\BigDecimal;

final readonly class TotalsAccumulator
{
    private function __construct(
        public BigDecimal $priceExtension,
        public BigDecimal $net,
        public BigDecimal $charge,
        public BigDecimal $discount,
        public BigDecimal $allocatedDiscount,
        public BigDecimal $tax,
        public BigDecimal $roundedTax,
        public BigDecimal $withholding,
        public BigDecimal $roundedWithholding,
    ) {}

    public static function zero(): self
    {
        $zero = BigDecimal::zero();

        return new self($zero, $zero, $zero, $zero, $zero, $zero, $zero, $zero, $zero);
    }

    public function addLine(BigDecimal $priceExtension, BigDecimal $net, BigDecimal $charge, BigDecimal $discount, BigDecimal $allocatedDiscount): self
    {
        return new self(
            $this->priceExtension->plus($priceExtension),
            $this->net->plus($net),
            $this->charge->plus($charge),
            $this->discount->plus($discount),
            $this->allocatedDiscount->plus($allocatedDiscount),
            $this->tax,
            $this->roundedTax,
            $this->withholding,
            $this->roundedWithholding,
        );
    }

    public function addTax(BigDecimal $amount, BigDecimal $rounded): self
    {
        return new self(
            $this->priceExtension,
            $this->net,
            $this->charge,
            $this->discount,
            $this->allocatedDiscount,
            $this->tax->plus($amount),
            $this->roundedTax->plus($rounded),
            $this->withholding,
            $this->roundedWithholding,
        );
    }

    public function addWithholding(BigDecimal $amount, BigDecimal $rounded): self
    {
        return new self(
            $this->priceExtension,
            $this->net,
            $this->charge,
            $this->discount,
            $this->allocatedDiscount,
            $this->tax,
            $this->roundedTax,
            $this->withholding->plus($amount),
            $this->roundedWithholding->plus($rounded),
        );
    }
}
