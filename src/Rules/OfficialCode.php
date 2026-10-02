<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Support\Catalogs;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class OfficialCode implements ValidationRule
{
    public function __construct(private Catalog $catalog) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! \is_string($value) || resolve(Catalogs::class)->find($this->catalog, $value) === null) {
            $fail('efatura::efatura.validation.official_code')->translate();
        }
    }
}
