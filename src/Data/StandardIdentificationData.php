<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

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
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'gtin'       => ['nullable', 'required_without_all:' . $field('ean') . ',' . $field('upc') . ',' . $field('pharmacode'), 'prohibits:' . $field('ean') . ',' . $field('upc') . ',' . $field('pharmacode'), ...FiscalRules::code()],
            'ean'        => ['nullable', 'required_without_all:' . $field('gtin') . ',' . $field('upc') . ',' . $field('pharmacode'), 'prohibits:' . $field('gtin') . ',' . $field('upc') . ',' . $field('pharmacode'), ...FiscalRules::code()],
            'upc'        => ['nullable', 'required_without_all:' . $field('gtin') . ',' . $field('ean') . ',' . $field('pharmacode'), 'prohibits:' . $field('gtin') . ',' . $field('ean') . ',' . $field('pharmacode'), ...FiscalRules::code()],
            'pharmacode' => ['nullable', 'required_without_all:' . $field('gtin') . ',' . $field('ean') . ',' . $field('upc'), 'prohibits:' . $field('gtin') . ',' . $field('ean') . ',' . $field('upc'), ...FiscalRules::code()],
        ];
    }
}
