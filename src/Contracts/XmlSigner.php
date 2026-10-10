<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Signing\SignedXml;
use Akira\Efatura\Signing\SigningCredentials;
use SensitiveParameter;

interface XmlSigner
{
    public function sign(
        string $xml,
        #[SensitiveParameter]
        SigningCredentials $credentials,
        SignatureProfile $profile = SignatureProfile::Enveloped,
    ): SignedXml;
}
