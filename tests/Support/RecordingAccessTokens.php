<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Enums\OAuthScope;
use Akira\Efatura\OAuth\AccessToken;
use Carbon\CarbonImmutable;

final class RecordingAccessTokens implements AccessTokenProvider
{
    /** @var list<array{string, string, list<string>}> */
    public array $calls = [];

    private int $generation = 1;

    public function __construct(private readonly string $salt) {}

    public function token(string $emitterTaxId, array $scopes): AccessToken
    {
        $this->calls[] = ['token', $emitterTaxId, self::values($scopes)];

        return new AccessToken($this->current(), CarbonImmutable::now()->addMinutes(5));
    }

    public function forget(string $emitterTaxId, array $scopes): void
    {
        $this->calls[] = ['forget', $emitterTaxId, self::values($scopes)];
        $this->generation++;
    }

    public function current(): string
    {
        return 'platform-token-' . $this->generation . '-' . $this->salt;
    }

    /**
     * @param  list<OAuthScope> $scopes
     * @return list<string>
     */
    private static function values(array $scopes): array
    {
        return array_map(static fn (OAuthScope $scope): string => $scope->value, $scopes);
    }
}
