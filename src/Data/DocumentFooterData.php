<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;

final class DocumentFooterData extends FiscalData
{
    /**
     * @param list<ExtraFieldData> $extraFields
     */
    public function __construct(
        public readonly ?string $note = null,
        #[DataCollectionOf(ExtraFieldData::class), ListType]
        public readonly array $extraFields = [],
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return ['note' => FiscalRules::text(10, 500)];
    }
}
