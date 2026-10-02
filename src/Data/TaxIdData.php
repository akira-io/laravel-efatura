<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Rules\ValidTaxId;
use Spatie\LaravelData\Data;

final class TaxIdData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $value,
        public readonly string $countryCode,
    ) {
        $this->validateFiscalFields(['value' => ['required', new ValidTaxId($countryCode)], ...self::rules()]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['countryCode' => ['required', new OfficialCode(Catalog::Countries)]];
    }
}
