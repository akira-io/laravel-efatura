<?php

declare(strict_types=1);

namespace Akira\Efatura\DataPipes;

use Akira\Efatura\Casts\FiscalDateCast;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataPipes\DataPipe;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataClass;

final readonly class FiscalDatesDataPipe implements DataPipe
{
    /**
     * @param  array<array-key, mixed> $properties
     * @param  CreationContext<Data>   $creationContext
     * @return array<array-key, mixed>
     */
    public function handle(mixed $payload, DataClass $class, array $properties, CreationContext $creationContext): array
    {
        foreach ($class->properties as $property) {
            $name  = $creationContext->mapPropertyNames && $property->inputMappedName !== null ? $property->inputMappedName : $property->name;
            $value = $properties[$name] ?? null;

            if ($property->cast instanceof FiscalDateCast && $value instanceof CarbonInterface) {
                $properties[$name] = $property->cast->formatted($value);
            }
        }

        return $properties;
    }
}
