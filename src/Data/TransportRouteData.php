<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class TransportRouteData extends Data
{
    use ValidatesFiscalFields;

    /**
     * @param list<TransportLocationData> $locations
     */
    public function __construct(
        #[DataCollectionOf(TransportLocationData::class)]
        public readonly array $locations,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['locations' => ['required', 'array', 'list', 'min:2']];
    }
}
