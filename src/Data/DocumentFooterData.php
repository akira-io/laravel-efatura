<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;

final class DocumentFooterData extends Data
{
    use ValidatesFiscalFields;

    /**
     * @param list<ExtraFieldData> $extraFields
     */
    public function __construct(public readonly ?string $note = null, public readonly array $extraFields = [])
    {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['note' => ['nullable', ...FiscalRules::text(10, 500)], 'extraFields' => ['array', 'list']];
    }
}
