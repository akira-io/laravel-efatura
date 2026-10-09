<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\BigDecimalCast;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\ValidationPayload;
use Akira\Efatura\Transformers\BigDecimalTransformer;
use Brick\Math\BigDecimal;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class QuantityData extends FiscalData
{
    public function __construct(
        #[WithCast(BigDecimalCast::class)]
        #[WithTransformer(BigDecimalTransformer::class)]
        public readonly BigDecimal $value,
        public readonly string $unitCode,
        public readonly bool $isStandardUnitCode = false,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context, Catalogs $catalogs): array
    {
        $unitCode = ['regex:/\A[A-z0-9]{1,10}\z/'];

        return [
            'value'    => [FiscalNumber::nonNegative()],
            'unitCode' => ValidationPayload::isTrue($context, 'isStandardUnitCode') ? [...$unitCode, new OfficialCode(Catalog::Units, $catalogs)] : $unitCode,
        ];
    }
}
