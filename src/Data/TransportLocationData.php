<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\TransportMode;
use Akira\Efatura\Support\FiscalRules;

final class TransportLocationData extends FiscalData
{
    public function __construct(
        public readonly AddressData $address,
        public readonly DurationData $duration,
        public readonly TransportMode $transportModeCode,
        public readonly ?string $vehicleRegistrationCode = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return ['vehicleRegistrationCode' => FiscalRules::code()];
    }
}
