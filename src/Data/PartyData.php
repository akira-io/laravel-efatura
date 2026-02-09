<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\Trans;
use Spatie\LaravelData\Data;

final class PartyData extends Data
{
    public function __construct(
        public readonly string $nif,
        public readonly string $name,
        public readonly ?string $address = null,
        public readonly ?string $city = null,
        public readonly ?string $country = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'nif'  => ['bail', 'required', 'string'],
            'name' => ['bail', 'required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'nif.required'  => Trans::get('efatura.validation.party_nif_required'),
            'name.required' => Trans::get('efatura.validation.party_name_required'),
        ];
    }

    public static function stopOnFirstFailure(): bool
    {
        return true;
    }
}
