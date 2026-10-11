<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Enums\SignatureProfile;
use DOMDocument;

interface DocumentSchemaValidator extends SchemaValidator
{
    public function validateDocument(DOMDocument $document, SignatureProfile $profile = SignatureProfile::Enveloped): void;
}
