<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\Fiscal;
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
        return ['value' => ['required', 'regex:/\A(?:CV[0-9][0-9]{2}(?:0[1-9]|1[012])(?:0[1-9]|[12][0-9]|3[01])[1-9][0-9]{35}|[1-9]\/[0-9]{4}\/[aA-zZ0-9]+(?:[_-][aA-zZ0-9]+)*\/[0-9]{1,9})\z/']];
    }
}
