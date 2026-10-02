<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Spatie\LaravelData\Data;

final readonly class DataInstances implements ValidationRule
{
    /**
     * @param class-string<Data> $class
     */
    public function __construct(private string $class) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! \is_array($value) || ! collect($value)->every(fn (mixed $item): bool => $item instanceof $this->class)) {
            $fail('efatura::efatura.validation.data_instances')->translate();
        }
    }
}
