<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\FiscalDateCast;
use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Rules\FiscalDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class DurationData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[WithCast(FiscalDateCast::class, 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d', '')]
        public readonly CarbonImmutable $startDate,
        #[WithCast(FiscalDateCast::class, 'H:i:s')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'H:i:s', '')]
        public readonly CarbonImmutable $startTime,
        #[WithCast(FiscalDateCast::class, 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d', '')]
        public readonly ?CarbonImmutable $endDate = null,
        #[WithCast(FiscalDateCast::class, 'H:i:s')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'H:i:s', '')]
        public readonly ?CarbonImmutable $endTime = null,
    ) {
        $this->validateFiscalFields(self::rules());
        if ($endDate instanceof CarbonImmutable && $endTime instanceof CarbonImmutable) {
            Validator::make([
                'start' => $startDate->format('Y-m-d') . 'T' . $startTime->format('H:i:s'),
                'end'   => $endDate->format('Y-m-d') . 'T' . $endTime->format('H:i:s'),
            ], ['end' => ['after_or_equal:start']])->validate();
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'startDate' => ['required', new FiscalDate],
            'startTime' => ['required', new FiscalDate('H:i:s')],
            'endDate'   => ['nullable', 'required_with:endTime', new FiscalDate],
            'endTime'   => ['nullable', 'required_with:endDate', new FiscalDate('H:i:s')],
        ];
    }
}
