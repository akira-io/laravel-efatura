<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\TransportDocumentType;
use Akira\Efatura\Enums\TransportReceiverType;

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
        public readonly array $lines,
        public readonly TransportRouteData $transportRoute,
        public readonly ?TransportReceiverType $receiverTypeCode = null,
        public readonly ?PartyData $receiver = null,
        public readonly array $references = [],
        public readonly ?EmissionContextData $emission = null,
        public readonly ?DocumentFooterData $footer = null,
    ) {
        $this->validateDocument();
    }

    public function type(): DocumentType
    {
        return DocumentType::Transport;
    }
}
