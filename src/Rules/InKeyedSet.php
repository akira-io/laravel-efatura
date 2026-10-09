<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class InKeyedSet implements ValidationRule
{
    /** @var array<string, true> */
    private array $members;

    /**
     * @param iterable<array-key, int|string> $members
     */
    public function __construct(iterable $members)
    {
        $keyed = [];
        foreach ($members as $member) {
            $keyed[(string) $member] = true;
        }

        $this->members = $keyed;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! \is_scalar($value) || ! isset($this->members[(string) $value])) {
            $fail('validation.in')->translate();
        }
    }
}
