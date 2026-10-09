<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class FiscalDate implements ValidationRule
{
    public function __construct(private string $format = Fiscal::DATE_FORMAT, private bool $instant = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof CarbonInterface) {
            $value = Fiscal::format($value, $this->format, $this->instant);
        }

        if (! \is_string($value) || ! Fiscal::parse($value, $this->format) instanceof CarbonInterface) {
            $fail('efatura::efatura.validation.fiscal_date')->translate();
        }
    }
}
