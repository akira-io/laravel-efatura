<?php

declare(strict_types=1);

namespace App\Data;

use Akira\Efatura\Packaging\PreparedDocument;
use Akira\Efatura\Signing\SignatureVerification;
use Akira\Efatura\Xml\XmlValidation;

final readonly class SignedDocument
{
    public function __construct(
        public PreparedDocument $prepared,
        public SignatureVerification $signature,
        public XmlValidation $validation,
    ) {}
}
