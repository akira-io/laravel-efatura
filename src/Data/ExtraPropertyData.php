<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;

final class ExtraPropertyData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['name' => ['required', ...FiscalRules::code()], 'value' => ['required', 'string']];
    }
}
