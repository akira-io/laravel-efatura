<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Enums\SignatureProfile;

interface SchemaValidator
{
    public function validate(string $xml, SignatureProfile $profile = SignatureProfile::Enveloped): void;
}
