<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Contracts\DocumentTypePolicy;
use Akira\Efatura\Enums\DocumentType;

final class DefaultDocumentTypePolicy implements DocumentTypePolicy
{
    public function supportsEmission(DocumentType $type): bool
    {
        return match ($type) {
            DocumentType::ELECTRONIC_INVOICE,
            DocumentType::ELECTRONIC_INVOICE_RECEIPT,
            DocumentType::ELECTRONIC_SALES_TICKET,
            DocumentType::ELECTRONIC_CREDIT_NOTE,
            DocumentType::ELECTRONIC_TRANSPORT_DOCUMENT => true,
            default                                     => false,
        };
    }

    public function allowsIud(DocumentType $type): bool
    {
        return $this->supportsEmission($type);
    }

    public function allowsXml(DocumentType $type): bool
    {
        return $this->supportsEmission($type);
    }

    public function allowedInProduction(DocumentType $type): bool
    {
        return $this->supportsEmission($type);
    }
}
