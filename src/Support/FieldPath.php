<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final class FieldPath
{
    /**
     * @param CreationContext<Data> $context
     */
    public static function of(CreationContext $context, DataProperty|string $property): string
    {
        $name = $property instanceof DataProperty ? $property->inputMappedName ?? $property->name : $property;

        return implode('.', [...$context->currentPath, $name]);
    }
}
