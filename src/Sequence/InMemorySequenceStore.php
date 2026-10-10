<?php

declare(strict_types=1);

namespace Akira\Efatura\Sequence;

use Akira\Efatura\Contracts\SequenceStore;

final class InMemorySequenceStore implements SequenceStore
{
    /** @var array<string, int> */
    private array $numbers = [];

    public function next(SequenceScope $scope): int
    {
        return $this->numbers[$scope->key()] = $scope->allocated($this->current($scope) + 1);
    }

    public function current(SequenceScope $scope): int
    {
        return $this->numbers[$scope->key()] ?? 0;
    }

    public function continueAfter(SequenceScope $scope, int $number): void
    {
        $this->numbers[$scope->key()] = $number;
    }
}
