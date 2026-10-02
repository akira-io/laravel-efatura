<?php

declare(strict_types=1);

namespace Akira\Efatura\Data\Contracts;

use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\TotalsData;

interface HasTotals
{
    /** @var list<LineItemData> */
    public array $lines { get; }

    public TotalsData $totals { get; }
}
