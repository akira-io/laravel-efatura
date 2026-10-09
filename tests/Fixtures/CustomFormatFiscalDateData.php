<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Fixtures;

use Akira\Efatura\Casts\FiscalDateCast;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class CustomFormatFiscalDateData extends Data
{
    public function __construct(
        #[WithCast(FiscalDateCast::class, 'd/m/Y')]
        public readonly CarbonImmutable $date,
    ) {}
}
