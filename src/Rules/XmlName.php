<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Support\FiscalRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class XmlName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (\is_string($value) && ! FiscalRules::isXmlName($value)) {
            $fail('efatura::efatura.validation.xml_name_invalid')->translate();
        }
    }
}
