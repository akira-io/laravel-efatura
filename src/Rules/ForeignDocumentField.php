<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ForeignDocumentField implements ValidationRule
{
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fail('efatura::efatura.validation.document_field_forbidden')->translate();
    }
}
