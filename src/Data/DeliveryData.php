<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Carbon\CarbonImmutable;

final class DeliveryData extends FiscalData
{
    public function __construct(
        #[FiscalDateFormat(Fiscal::DATE_FORMAT)]
        public readonly CarbonImmutable $deliveryDate,
        public readonly AddressData $address,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return ['deliveryDate' => [new FiscalDate]];
    }
}
