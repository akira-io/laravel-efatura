<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\DocumentType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class EventNumberRangeData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly int $ledCode,
        public readonly string $serie,
        public readonly DocumentType $documentTypeCode,
        public readonly int $documentNumberStart,
        public readonly int $documentNumberEnd,
        public readonly ?int $year = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'ledCode'             => ['required', 'integer', 'between:1,99999'],
            'serie'               => ['required', 'string', 'max:20', 'regex:/\A[aA-zZ0-9]+(?:[_-][aA-zZ0-9]+)*\z/'],
            'documentNumberStart' => ['required', 'integer', 'between:1,999999999'],
            'documentNumberEnd'   => ['required', 'integer', 'between:1,999999999', 'gte:' . $field('documentNumberStart')],
            'year'                => ['nullable', 'integer', 'between:2021,2099'],
        ];
    }
}
