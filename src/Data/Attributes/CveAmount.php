<?php

declare(strict_types=1);

namespace Akira\Efatura\Data\Attributes;

use Akira\Efatura\Money\MoneyCast;
use Akira\Efatura\Support\Fiscal;
use Attribute;
use Spatie\LaravelData\Attributes\WithCastAndTransformer;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class CveAmount extends WithCastAndTransformer
{
    public function __construct()
    {
        parent::__construct(MoneyCast::class, Fiscal::CURRENCY, Fiscal::AMOUNT_SCALE, false);
    }
}
