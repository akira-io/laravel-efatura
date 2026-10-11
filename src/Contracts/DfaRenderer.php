<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Dfa\DfaData;
use Akira\Efatura\Dfa\RenderedDfa;

interface DfaRenderer
{
    public function render(DfaData $data): RenderedDfa;
}
