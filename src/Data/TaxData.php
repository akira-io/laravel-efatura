<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\Trans;
use Spatie\LaravelData\Data;

final class TaxData extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly float $rate,
        public readonly float $amount,
        public readonly ?string $exemptionReason = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'exemptionReason' => ['bail', 'required_if:type,NA'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'exemptionReason.required_if' => Trans::get('efatura.validation.na_tax_exemption_required'),
        ];
    }

    public static function stopOnFirstFailure(): bool
    {
        return true;
    }
}
