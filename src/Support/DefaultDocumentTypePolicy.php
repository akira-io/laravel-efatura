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
            DocumentType::Invoice,
            DocumentType::InvoiceReceipt,
            DocumentType::SalesReceipt,
            DocumentType::CreditNote,
            DocumentType::Transport => true,
            default                 => false,
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
