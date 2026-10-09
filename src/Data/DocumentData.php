<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Actions\ValidateDocumentCompatibilityAction;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\PartyReference;
use Akira\Efatura\Rules\ForeignDocumentField;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\ValidationPayload;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\Rule;
use Override;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Validation\ValidationContext;

abstract class DocumentData extends FiscalData
{
    abstract public DocumentHeaderData $header { get; }

    abstract public PartyData $emitter { get; }

    abstract public ?PartyData $receiver { get; }

    abstract public ?EmissionContextData $emission { get; }

    final public static function documentType(): DocumentType
    {
        return DocumentType::fromDataClass(static::class);
    }

    final public function type(): DocumentType
    {
        return self::documentType();
    }

    #[Override]
    final public static function from(mixed ...$payloads): static
    {
        $document = parent::from(...$payloads);
        resolve(ValidateDocumentCompatibilityAction::class)->handle($document);

        return $document;
    }

    /**
     * @param Arrayable<array-key, mixed>|array<array-key, mixed> $payload
     */
    #[Override]
    final public static function validateAndCreate(Arrayable|array $payload): static
    {
        return self::from($payload);
    }

    /**
     * @return array<string, list<mixed>>
     */
    final public static function rules(ValidationContext $context, DataConfig $config): array
    {
        return self::mergedRules(
            self::emitterRules($context),
            self::receiverRules($context),
            self::foreignFieldRules($context, $config),
            static::documentRules($context),
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    abstract protected static function documentRules(ValidationContext $context): array;

    /**
     * @param  array<string, list<mixed>> ...$ruleSets
     * @return array<string, list<mixed>>
     */
    private static function mergedRules(array ...$ruleSets): array
    {
        $merged = [];
        foreach ($ruleSets as $ruleSet) {
            foreach ($ruleSet as $field => $rules) {
                $merged[$field] = [...$merged[$field] ?? [], ...$rules];
            }
        }

        return $merged;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function receiverRules(ValidationContext $context): array
    {
        $rules     = ['receiver' => [Rule::requiredIf(\is_array(ValidationPayload::value($context, 'header.selfBilling')))]];
        $reference = ValidationPayload::value($context, 'receiver.reference');

        if ($reference === null || $reference === PartyReference::Emitter->value) {
            return $rules;
        }

        return [...$rules, 'receiver.reference' => [Rule::in([PartyReference::Emitter->value])]];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function emitterRules(ValidationContext $context): array
    {
        $rules = [
            'emitter.taxId'               => ['required'],
            'emitter.address'             => ['required'],
            'emitter.contacts'            => ['required'],
            'emitter.reference'           => ['prohibited'],
            'emitter.taxId.countryCode'   => ['in:' . Fiscal::COUNTRY],
            'emitter.address.countryCode' => ['in:' . Fiscal::COUNTRY],
        ];

        if (! \is_array(ValidationPayload::value($context, 'emitter.contacts'))) {
            return $rules;
        }

        return [
            ...$rules,
            'emitter.contacts.email'     => ['required'],
            'emitter.contacts.telephone' => ['required_without:' . FieldPath::of($context, 'emitter.contacts.mobilephone')],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function foreignFieldRules(ValidationContext $context, DataConfig $config): array
    {
        $fields = $config->getDataClass(static::class)->properties
            ->flatMap(static fn (DataProperty $property): array => [$property->name, $property->inputMappedName ?? $property->name]);

        return collect(\is_array($context->payload) ? array_keys($context->payload) : [])
            ->diff($fields)
            ->mapWithKeys(static fn (int|string $field): array => [(string) $field => [new ForeignDocumentField]])
            ->all();
    }
}
