<?php

declare(strict_types=1);

namespace App\Data;

final readonly class TestCertificate
{
    public function __construct(public string $certificatePem, public string $privateKeyPem) {}
}
