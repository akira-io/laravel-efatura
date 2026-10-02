<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\FiscalDateCast;
use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Rules\FiscalDate;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class DeliveryData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[WithCast(FiscalDateCast::class, 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d', '')]
        public readonly CarbonImmutable $deliveryDate,
        public readonly AddressData $address,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['deliveryDate' => ['required', new FiscalDate]];
    }
}
