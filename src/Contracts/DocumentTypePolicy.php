<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Enums\DocumentType;

interface DocumentTypePolicy
{
    public function supportsEmission(DocumentType $type): bool;

    public function allowsIud(DocumentType $type): bool;

    public function allowsXml(DocumentType $type): bool;

    public function allowedInProduction(DocumentType $type): bool;
}
