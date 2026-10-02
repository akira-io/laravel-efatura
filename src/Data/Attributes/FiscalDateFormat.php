<?php

declare(strict_types=1);

namespace Akira\Efatura\Data\Attributes;

use Akira\Efatura\Casts\FiscalDateCast;
use Attribute;
use Spatie\LaravelData\Attributes\WithCastAndTransformer;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class FiscalDateFormat extends WithCastAndTransformer
{
    public function __construct(string $format, bool $instant = false)
    {
        parent::__construct(FiscalDateCast::class, $format, $instant);
    }
}
