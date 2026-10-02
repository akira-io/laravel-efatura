<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\TransportDocumentType;
use Akira\Efatura\Enums\TransportReceiverType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Min;

final class TransportDocumentData extends InvoiceData
{
    /**
     * @param list<LineItemData>  $lines
     * @param list<ReferenceData> $references
     */
    public function __construct(
        public readonly DocumentHeaderData $header,
        public readonly PartyData $emitter,
        public readonly TransportDocumentType $transportDocumentTypeCode,
        public readonly PartyData $transportServiceProvider,
        #[DataCollectionOf(LineItemData::class), ListType, Min(1)]
        public readonly array $lines,
        public readonly TransportRouteData $transportRoute,
        public readonly ?TransportReceiverType $receiverTypeCode = null,
        public readonly ?PartyData $receiver = null,
        #[DataCollectionOf(ReferenceData::class), ListType]
        public readonly array $references = [],
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {}

    public function type(): DocumentType
    {
        return DocumentType::Transport;
    }
}
