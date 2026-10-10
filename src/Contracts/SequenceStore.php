<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Sequence\SequenceScope;

interface SequenceStore
{
    public function next(SequenceScope $scope): int;
}
