<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Rules\ValidTaxId;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\ValidationPayload;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class TaxIdData extends FiscalData
{
    public function __construct(
        public readonly string $value,
        public readonly string $countryCode,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context, Catalogs $catalogs): array
    {
        return [
            'value'       => [new ValidTaxId(ValidationPayload::string($context, 'countryCode'))],
            'countryCode' => [new OfficialCode(Catalog::Countries, $catalogs)],
        ];
    }
}
