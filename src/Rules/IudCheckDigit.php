<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Support\Luhn;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class IudCheckDigit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (\is_string($value) && FiscalRules::isIud($value) && ! Luhn::passes(substr($value, 2))) {
            $fail('efatura::efatura.validation.iud_invalid')->translate();
        }
    }
}
