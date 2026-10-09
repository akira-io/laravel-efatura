<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\PartyReference;
use Akira\Efatura\Enums\TransportDocumentType;
use Akira\Efatura\Enums\TransportReceiverType;
use Akira\Efatura\Support\DocumentRuleSets;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\ValidationPayload;
use Illuminate\Validation\Rule;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class TransportDocumentData extends DocumentData
{
    /**
     * @param list<LineItemData>  $lines
     * @param list<ReferenceData> $references
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        #[MapName('transportDocumentTypeCode')]
        public readonly TransportDocumentType $transportDocumentType,
        public readonly PartyData $transportServiceProvider,
        #[DataCollectionOf(LineItemData::class), ListType, Min(1), Max(Fiscal::MAX_LINES)]
        public readonly array $lines,
        public readonly TransportRouteData $transportRoute,
        #[MapName('receiverTypeCode')]
        public readonly ?TransportReceiverType $receiverType = null,
        public readonly ?PartyData $receiver = null,
        #[DataCollectionOf(ReferenceData::class), ListType, Max(Fiscal::MAX_REFERENCES)]
        public readonly array $references = [],
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    #[Override]
    protected static function documentRules(ValidationContext $context): array
    {
        $receiverType       = ValidationPayload::enum($context, 'receiverTypeCode', TransportReceiverType::class);
        $undetermined       = $receiverType === TransportReceiverType::Undetermined;
        $providerIsReceiver = ValidationPayload::string($context, 'transportServiceProvider.reference') === PartyReference::Receiver->value;
        $rules              = [
            ...DocumentRuleSets::lines($context, self::documentType()),
            'receiver' => [Rule::requiredIf(! $undetermined || $providerIsReceiver), Rule::prohibitedIf($undetermined)],
        ];

        if ($receiverType !== TransportReceiverType::Taxpayer || ! \is_array(ValidationPayload::value($context, 'receiver'))
            || ValidationPayload::string($context, 'receiver.reference') === PartyReference::Emitter->value) {
            return $rules;
        }

        return [...$rules, 'receiver.taxId.countryCode' => ['required', 'in:' . Fiscal::COUNTRY]];
    }
}
