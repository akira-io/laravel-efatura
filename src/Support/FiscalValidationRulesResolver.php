<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Illuminate\Support\Arr;
use Override;
use Spatie\LaravelData\Resolvers\DataValidationRulesResolver;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Validation\DataRules;
use Spatie\LaravelData\Support\Validation\ValidationPath;

final class FiscalValidationRulesResolver extends DataValidationRulesResolver
{
    /**
     * @param  array<array-key, mixed> $fullPayload
     * @return array<array-key, mixed>
     */
    #[Override]
    public function execute(string $class, array $fullPayload, ValidationPath $path, DataRules $dataRules): array
    {
        if (! $path->isRoot() && ValidatedData::isMarked(Arr::get($fullPayload, $path->get()))) {
            return $dataRules->rules;
        }

        return parent::execute($class, $fullPayload, $path, $dataRules);
    }

    /**
     * @param array<array-key, mixed> $fullPayload
     */
    #[Override]
    protected function resolveDynamicCollectionRules(DataProperty $dataProperty, array $fullPayload, ValidationPath $propertyPath, DataRules $dataRules): void
    {
        foreach (Arr::wrap(Arr::get($fullPayload, $propertyPath->get())) as $key => $item) {
            if (! \is_array($item)) {
                $dataRules->add($propertyPath->property('*'), ['array']);

                continue;
            }

            $this->execute((string) $dataProperty->type->dataClass, $fullPayload, $propertyPath->property((string) $key), $dataRules);
        }
    }
}
