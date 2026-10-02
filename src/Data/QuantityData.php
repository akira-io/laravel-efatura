<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Money\BigDecimalCast;
use Akira\Efatura\Money\BigDecimalTransformer;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Brick\Math\BigDecimal;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class QuantityData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[WithCast(BigDecimalCast::class)]
        #[WithTransformer(BigDecimalTransformer::class)]
        public readonly BigDecimal $value,
        public readonly string $unitCode,
        public readonly bool $isStandardUnitCode = false,
    ) {
        $rules = self::rules();
        if ($isStandardUnitCode) {
            $rules['unitCode'][] = new OfficialCode(Catalog::Units);
        }

        $this->validateFiscalFields($rules);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['value' => ['required', new FiscalNumber], 'unitCode' => ['required', 'regex:/\A[aA-zZ0-9]{1,10}\z/']];
    }
}
