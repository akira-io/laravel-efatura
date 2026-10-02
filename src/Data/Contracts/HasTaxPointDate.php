<?php

declare(strict_types=1);

namespace Akira\Efatura\Data\Contracts;

use Carbon\CarbonImmutable;

interface HasTaxPointDate
{
    public ?CarbonImmutable $taxPointDate { get; }
}
