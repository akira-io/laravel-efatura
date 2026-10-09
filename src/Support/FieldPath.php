<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class FieldPath
{
    /**
     * @param CreationContext<Data>|ValidationContext $context
     */
    public static function of(CreationContext|ValidationContext $context, DataProperty|string $property): string
    {
        $name = $property instanceof DataProperty ? self::name($property) : $property;

        if ($context instanceof ValidationContext) {
            return $context->path->property($name)->get() ?? $name;
        }

        return implode('.', [...$context->currentPath, $name]);
    }

    public static function name(DataProperty $property): string
    {
        return $property->inputMappedName ?? $property->name;
    }

    public static function list(ValidationContext $context, string ...$properties): string
    {
        return collect($properties)->map(static fn (string $property): string => self::of($context, $property))->implode(',');
    }
}
