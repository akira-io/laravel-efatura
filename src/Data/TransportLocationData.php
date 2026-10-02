<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\TransportMode;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Attributes\MapName;

final class TransportLocationData extends FiscalData
{
    public function __construct(
        public readonly AddressData $address,
        public readonly DurationData $duration,
        #[MapName('transportModeCode')]
        public readonly TransportMode $transportMode,
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
