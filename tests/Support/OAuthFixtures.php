<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\RefreshTokenRepository;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

final class OAuthFixtures
{
    public const string ISSUER = 'https://iam.efatura.cv/auth/realms/taxpayers';

    public const string DISCOVERY_URL = self::ISSUER . '/.well-known/openid-configuration';

    public const string TOKEN_URL = self::ISSUER . '/protocol/openid-connect/token';

    public const string EMITTER = '100200300';

    public const string CLIENT_ID = 'transmitter-client';

    /**
     * @param array<array-key, mixed> $overrides
     */
    public static function configure(InMemoryRefreshTokens $refreshTokens, string $clientSecret, array $overrides = []): EfaturaConfig
    {
        $config = ConfigFixtures::load(array_replace_recursive([
            'transmitter' => ['oauth' => ['client_id' => self::CLIENT_ID, 'client_secret' => $clientSecret]],
            'cache'       => ['store' => 'array'],
            'http'        => ['retry_delay_milliseconds' => 0],
        ], $overrides));
        config()->set('app.key', config('app.key') ?: 'base64:' . base64_encode(random_bytes(32)));

        app()->instance(EfaturaConfig::class, $config);
        app()->instance(RefreshTokenRepository::class, $refreshTokens);

        return $config;
    }

    public static function discovery(string $tokenEndpoint = self::TOKEN_URL): PromiseInterface
    {
        return Http::response(['issuer' => self::ISSUER, 'token_endpoint' => $tokenEndpoint, 'grant_types_supported' => ['authorization_code']]);
    }

    /**
     * @param array<string, mixed> $grant
     */
    public static function grant(string $accessToken, int $expiresIn = 300, array $grant = []): PromiseInterface
    {
        return Http::response(['access_token' => $accessToken, 'token_type' => 'Bearer', 'expires_in' => $expiresIn, ...$grant]);
    }
}
