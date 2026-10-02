<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

final readonly class FiscalDate implements ValidationRule
{
    public function __construct(private string $format = 'Y-m-d') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof CarbonImmutable) {
            $value = $value->format($this->format);
        }

        $rules = ['required', 'string', 'date_format:' . $this->format];
        if ($this->format === 'Y-m-d') {
            $rules[] = 'after_or_equal:2021-01-01';
        }

        if (Validator::make(['value' => $value], ['value' => $rules])->fails()) {
            $fail('efatura::efatura.validation.fiscal_date')->translate();
        }
    }
}
