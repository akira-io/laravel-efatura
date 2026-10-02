<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

final readonly class ValidTaxId implements ValidationRule
{
    public function __construct(private string $countryCode) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = $this->countryCode === 'CV' ? '/\A[1-9][0-9]{8}\z/u' : '/\A[^\s]{5,20}\z/u';
        if (! \is_string($value) || ! Str::isMatch($pattern, $value)) {
            $fail('efatura::efatura.validation.tax_id')->translate();
        }
    }
}
