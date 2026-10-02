<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class EventNumberRangeData extends FiscalData
{
    public function __construct(
        public readonly int $ledCode,
        public readonly string $serie,
        public readonly DocumentType $documentTypeCode,
        public readonly int $documentNumberStart,
        public readonly int $documentNumberEnd,
        public readonly ?int $year = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'ledCode'             => FiscalRules::ledCode(),
            'serie'               => FiscalRules::series(),
            'documentNumberStart' => FiscalRules::documentNumber(),
            'documentNumberEnd'   => [...FiscalRules::documentNumber(), 'gte:' . FieldPath::of($context, 'documentNumberStart')],
            'year'                => ['integer', 'between:2021,2099'],
        ];
    }
}
