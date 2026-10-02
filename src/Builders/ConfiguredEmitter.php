<?php

declare(strict_types=1);

namespace Akira\Efatura\Builders;

use Akira\Efatura\Configuration\EmitterConfig;
use Illuminate\Support\Arr;

final readonly class ConfiguredEmitter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function party(?EmitterConfig $emitter): ?array
    {
        if (! $emitter instanceof EmitterConfig) {
            return null;
        }

        $address = Arr::whereNotNull(get_object_vars($emitter->address));

        return [
            'taxId'    => self::taxId($emitter), 'name' => $emitter->name,
            'address'  => $address === [] ? null : $address,
            'contacts' => ['email' => $emitter->contacts->email, 'telephone' => $emitter->contacts->telephone,
                'mobilephone'      => $emitter->contacts->mobile, 'telefax' => $emitter->contacts->telefax, 'website' => $emitter->contacts->website],
        ];
    }

    /**
     * @return array{value: ?string, countryCode: string}|null
     */
    public static function taxId(?EmitterConfig $emitter): ?array
    {
        return $emitter instanceof EmitterConfig ? ['value' => $emitter->taxId, 'countryCode' => 'CV'] : null;
    }
}
