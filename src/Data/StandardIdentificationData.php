<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

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
        return FiscalRules::exactlyOneOf($context, collect(['gtin', 'ean', 'upc', 'pharmacode'])
            ->mapWithKeys(static fn (string $field): array => [$field => FiscalRules::code()])->all());
    }
}
