<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class DeliveryData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
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
