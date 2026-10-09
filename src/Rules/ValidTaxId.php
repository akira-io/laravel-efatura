<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

final readonly class ValidTaxId implements ValidationRule
{
    public function __construct(private ?string $countryCode) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = $this->countryCode === Fiscal::COUNTRY ? '/\A' . FiscalRules::CV_TAX_ID . '\z/u' : '/\A\S{5,20}\z/u';
        if (! \is_string($value) || ! Str::isMatch($pattern, $value)) {
            $fail('efatura::efatura.validation.tax_id')->translate();
        }
    }
}
