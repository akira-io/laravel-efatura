<?php

declare(strict_types=1);

namespace Akira\Efatura\Contracts;

use SensitiveParameter;

interface RefreshTokenRepository
{
    public function find(string $emitterTaxId): ?string;

    public function replace(string $emitterTaxId, #[SensitiveParameter] string $refreshToken): void;
}
