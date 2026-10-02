<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Support\Catalogs;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class OfficialCode implements ValidationRule
{
    public function __construct(private string $catalog) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $catalogs = resolve(Catalogs::class);
        $record   = \is_string($value) ? match ($this->catalog) {
            'countries'             => $catalogs->country($value),
            'currencies'            => $catalogs->currency($value),
            'locations'             => $catalogs->location($value),
            'units'                 => $catalogs->unit($value),
            'payment_means'         => $catalogs->paymentMean($value),
            'tax_exemption_reasons' => $catalogs->taxExemptionReason($value),
            default                 => null,
        } : null;
        if ($record === null) {
            $fail('efatura::efatura.validation.official_code')->translate();
        }
    }
}
