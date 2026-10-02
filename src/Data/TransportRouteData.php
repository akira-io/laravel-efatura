<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Min;

final class TransportRouteData extends FiscalData
{
    /**
     * @param list<TransportLocationData> $locations
     */
    public function __construct(
        #[DataCollectionOf(TransportLocationData::class), ListType, Min(2)]
        public readonly array $locations,
    ) {}
}
