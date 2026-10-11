<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

final readonly class SelfBillingAuthorization
{
    public function __construct(
        public string $authorizationId,
        public int $expiresInSeconds,
        public string $iud,
        public string $series,
        public int $ledCode,
        public int $documentNumber,
    ) {}
}
