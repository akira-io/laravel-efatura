<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NotBlank implements ValidationRule
{
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (\is_string($value) && trim($value) === '') {
            $fail('validation.filled')->translate();
        }
    }
}
