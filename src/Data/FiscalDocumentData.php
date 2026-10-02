<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class FiscalDocumentData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $value,
        public readonly ?bool $isOldDocument = null,
    ) {
        $this->validateFiscalFields(self::rules());
        $this->validateFiscalFields(['isOldDocument' => [Rule::prohibitedIf($isOldDocument !== null && $isOldDocument === Str::startsWith($value, Fiscal::COUNTRY))]]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['value' => ['required', ...FiscalRules::fiscalDocumentReference()]];
    }
}
