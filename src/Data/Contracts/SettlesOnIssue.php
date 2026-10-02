<?php

declare(strict_types=1);

namespace Akira\Efatura\Data\Contracts;

use Akira\Efatura\Data\PaymentsData;

interface SettlesOnIssue
{
    public PaymentsData $payments { get; }
}
