<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Rules\UnreservedFiscalField;
use Spatie\LaravelData\Data;

final class ExtraFieldData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly ?string $namespace = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name'      => ['required', 'regex:/\A[\p{L}_][\p{L}\p{N}_.-]*\z/u', new UnreservedFiscalField],
            'value'     => ['present', 'string'],
            'namespace' => ['nullable', 'regex:/\A[a-zA-Z][a-zA-Z0-9+.-]*:[^\s]+\z/', 'not_in:urn:cv:efatura:xsd:v1.0'],
        ];
    }
}
