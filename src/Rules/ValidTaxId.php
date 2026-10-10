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
        if (! \is_string($value) || ! $this->accepts($value)) {
            $fail('efatura::efatura.validation.tax_id')->translate();
        }
    }

    private function accepts(string $value): bool
    {
        return $this->countryCode === Fiscal::COUNTRY ? FiscalRules::isCvTaxId($value) : Str::isMatch('/\A\S{5,20}\z/u', $value);
    }
}
