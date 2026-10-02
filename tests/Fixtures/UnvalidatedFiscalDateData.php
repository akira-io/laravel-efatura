<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Fixtures;

use Akira\Efatura\Casts\FiscalDateCast;
use DateTimeInterface;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class UnvalidatedFiscalDateData extends Data
{
    public function __construct(
        #[WithCast(FiscalDateCast::class)]
        #[WithTransformer(FiscalDateCast::class)]
        public readonly DateTimeInterface $date,
    ) {}
}
