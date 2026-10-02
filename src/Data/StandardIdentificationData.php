<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class StandardIdentificationData extends FiscalData
{
    public function __construct(
        public readonly ?string $gtin = null,
        public readonly ?string $ean = null,
        public readonly ?string $upc = null,
        public readonly ?string $pharmacode = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $others = FieldPath::list($context, 'ean', 'upc', 'pharmacode');

        return [
            'gtin'       => ['required_without_all:' . $others, 'prohibits:' . $others, ...FiscalRules::code()],
            'ean'        => ['required_without_all:' . FieldPath::list($context, 'gtin', 'upc', 'pharmacode'), 'prohibits:' . FieldPath::list($context, 'gtin', 'upc', 'pharmacode'), ...FiscalRules::code()],
            'upc'        => ['required_without_all:' . FieldPath::list($context, 'gtin', 'ean', 'pharmacode'), 'prohibits:' . FieldPath::list($context, 'gtin', 'ean', 'pharmacode'), ...FiscalRules::code()],
            'pharmacode' => ['required_without_all:' . FieldPath::list($context, 'gtin', 'ean', 'upc'), 'prohibits:' . FieldPath::list($context, 'gtin', 'ean', 'upc'), ...FiscalRules::code()],
        ];
    }
}
