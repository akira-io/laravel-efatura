<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Spatie\LaravelData\Data;

final class TotalsData extends Data
{
    public function __construct(
        public readonly float $subtotal,
        public readonly float $taxTotal,
        public readonly float $grandTotal,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'subtotal'   => ['bail', 'numeric', 'min:0'],
            'taxTotal'   => ['bail', 'numeric', 'min:0'],
            'grandTotal' => ['bail', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'subtotal.min'   => __('efatura.validation.totals_negative'),
            'taxTotal.min'   => __('efatura.validation.totals_negative'),
            'grandTotal.min' => __('efatura.validation.totals_negative'),
        ];
    }

    public static function stopOnFirstFailure(): bool
    {
        return true;
    }
}
