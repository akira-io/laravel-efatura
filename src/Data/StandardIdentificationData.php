<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;

final class StandardIdentificationData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly ?string $gtin = null,
        public readonly ?string $ean = null,
        public readonly ?string $upc = null,
        public readonly ?string $pharmacode = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'gtin'       => ['nullable', 'required_without_all:ean,upc,pharmacode', 'prohibits:ean,upc,pharmacode', ...FiscalRules::code()],
            'ean'        => ['nullable', 'required_without_all:gtin,upc,pharmacode', 'prohibits:gtin,upc,pharmacode', ...FiscalRules::code()],
            'upc'        => ['nullable', 'required_without_all:gtin,ean,pharmacode', 'prohibits:gtin,ean,pharmacode', ...FiscalRules::code()],
            'pharmacode' => ['nullable', 'required_without_all:gtin,ean,upc', 'prohibits:gtin,ean,upc', ...FiscalRules::code()],
        ];
    }
}
