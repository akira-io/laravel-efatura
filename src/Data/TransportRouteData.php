<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Rules\DataInstances;
use Spatie\LaravelData\Data;

final class TransportRouteData extends Data
{
    use ValidatesFiscalFields;

    /**
     * @param list<TransportLocationData> $locations
     */
    public function __construct(
        public readonly array $locations,
    ) {
        $rules                = self::rules();
        $rules['locations'][] = new DataInstances(TransportLocationData::class);
        $this->validateFiscalFields($rules);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['locations' => ['required', 'array', 'list', 'min:2']];
    }
}
