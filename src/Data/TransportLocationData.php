<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\TransportMode;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;

final class TransportLocationData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly AddressData $address,
        public readonly DurationData $duration,
        public readonly TransportMode $transportModeCode,
        public readonly ?string $vehicleRegistrationCode = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['vehicleRegistrationCode' => ['nullable', ...FiscalRules::code()]];
    }
}
