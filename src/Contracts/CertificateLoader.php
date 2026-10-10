<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use Akira\Efatura\Configuration\CertificateConfig;
use Akira\Efatura\Signing\SigningCredentials;
use SensitiveParameter;

interface CertificateLoader
{
    public function load(#[SensitiveParameter] CertificateConfig $config): SigningCredentials;
}
