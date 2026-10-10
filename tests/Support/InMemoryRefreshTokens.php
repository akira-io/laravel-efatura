<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Contracts\RefreshTokenRepository;
use SensitiveParameter;

final class InMemoryRefreshTokens implements RefreshTokenRepository
{
    /** @var list<array{string, string}> */
    public array $replaced = [];

    /**
     * @param array<string, string> $tokens
     */
    public function __construct(private array $tokens = []) {}

    public function find(string $emitterTaxId): ?string
    {
        return $this->tokens[$emitterTaxId] ?? null;
    }

    public function replace(string $emitterTaxId, #[SensitiveParameter] string $refreshToken): void
    {
        $this->tokens[$emitterTaxId] = $refreshToken;
        $this->replaced[]            = [$emitterTaxId, $refreshToken];
    }
}
