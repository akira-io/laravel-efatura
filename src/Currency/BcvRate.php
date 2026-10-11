<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

final readonly class BcvRate
{
    public function __construct(public CarbonImmutable $publishedOn, public BigDecimal $rate) {}
}
