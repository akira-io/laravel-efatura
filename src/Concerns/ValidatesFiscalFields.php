<?php

declare(strict_types=1);

namespace Akira\Efatura\Concerns;

use BackedEnum;
use Illuminate\Support\Facades\Validator;

trait ValidatesFiscalFields
{
    /**
     * @param array<string, array<int, mixed>> $rules
     */
    protected function validateFiscalFields(array $rules): void
    {
        $values = collect(get_object_vars($this))->map(static fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value)->all();
        foreach ($values as $attribute => $value) {
            if (\is_string($value) && collect($rules[$attribute] ?? [])->containsStrict('nullable')) {
                $rules[$attribute][] = 'required';
            }
        }

        Validator::make($values, $rules)->validate();
    }
}
