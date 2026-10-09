<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\DataPipes\FiscalDatesDataPipe;
use BackedEnum;
use Override;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataPipeline;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\Creation\CreationContextFactory;

#[MergeValidationRules]
abstract class FiscalData extends Data
{
    /**
     * @param  CreationContext<static>|null   $creationContext
     * @return CreationContextFactory<static>
     */
    #[Override]
    final public static function factory(?CreationContext $creationContext = null): CreationContextFactory
    {
        if ($creationContext instanceof CreationContext) {
            return parent::factory($creationContext);
        }

        return parent::factory()->alwaysValidate();
    }

    #[Override]
    final public static function pipeline(): DataPipeline
    {
        return parent::pipeline()->firstThrough(FiscalDatesDataPipe::class);
    }

    /**
     * @param  array<array-key, mixed> $properties
     * @return array<array-key, mixed>
     */
    #[Override]
    final public static function prepareForPipeline(array $properties): array
    {
        return array_map(self::normalized(...), $properties);
    }

    /**
     * @return array<array-key, mixed>
     */
    final public function toPayload(): array
    {
        return (clone $this)->setDataContext(null)->toArray();
    }

    private static function normalized(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toPayload();
        }

        if ($value instanceof Data) {
            return $value->toArray();
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return \is_array($value) ? array_map(self::normalized(...), $value) : $value;
    }
}
