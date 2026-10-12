<?php

declare(strict_types=1);

namespace Akira\Efatura\OAuth;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\RefreshTokenRepository;
use Akira\Efatura\Enums\OAuthScope;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\OAuthException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Transport\BoundedBody;
use Akira\Efatura\Transport\TransportRequests;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use JsonException;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

use const JSON_THROW_ON_ERROR;

final readonly class RefreshTokenAccessTokenProvider implements AccessTokenProvider
{
    private const int LOCK_MARGIN_SECONDS = 10;

    private const int EXPIRY_MARGIN_SECONDS = 60;

    private const string B64_TOKEN = '/\A[A-Za-z0-9\-._~+\/]+=*\z/';

    private const array REFUSED_STATUSES = [400, 401];

    public function __construct(
        private TransportRequests $requests,
        private BoundedBody $body,
        private OpenIdConfiguration $discovery,
        private CacheFactory $caches,
        private StringEncrypter $encryption,
        private RefreshTokenRepository $refreshTokens,
        private EfaturaConfig $config,
        private ClockInterface $clock,
    ) {}

    public function token(string $emitterTaxId, array $scopes): AccessToken
    {
        $key = $this->key($emitterTaxId, $scopes);

        return $this->cached($key) ?? $this->renewed($key, $emitterTaxId, $scopes);
    }

    public function forget(string $emitterTaxId, array $scopes): void
    {
        $this->cache()->forget($this->key($emitterTaxId, $scopes));
    }

    /**
     * @param list<OAuthScope> $scopes
     */
    private function renewed(string $key, string $emitterTaxId, array $scopes): AccessToken
    {
        $this->refreshToken($emitterTaxId);
        $this->credentials($emitterTaxId);
        $endpoint = $this->discovery->tokenEndpoint();

        return $this->locked($emitterTaxId, fn (): AccessToken => $this->cached($key) ?? $this->refresh($key, $emitterTaxId, $scopes, $endpoint));
    }

    /**
     * @param Closure(): AccessToken $refresh
     */
    private function locked(string $emitterTaxId, #[SensitiveParameter] Closure $refresh): AccessToken
    {
        $store = $this->cache()->getStore();
        if (! $store instanceof LockProvider) {
            return $refresh();
        }

        $seconds = $this->config->http->platform->timeoutSeconds + $this->config->http->platform->connectTimeoutSeconds;

        try {
            $token = $store->lock($this->lockKey($emitterTaxId), $seconds + self::LOCK_MARGIN_SECONDS)->block($seconds, $refresh);
        } catch (LockTimeoutException) {
            throw OAuthException::tokenUnavailable($emitterTaxId);
        }

        return $token instanceof AccessToken ? $token : $refresh();
    }

    private function cached(string $key): ?AccessToken
    {
        $payload = $this->cache()->get($key);

        try {
            $stored = \is_string($payload) ? json_decode($this->encryption->decryptString($payload), true, Fiscal::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR) : null;
        } catch (DecryptException|JsonException) {
            return null;
        }

        $value     = \is_array($stored) ? ($stored['value'] ?? null) : null;
        $expiresAt = \is_array($stored) ? ($stored['expires_at'] ?? null) : null;
        if (! \is_string($value) || ! \is_int($expiresAt) || $expiresAt <= $this->now()->getTimestamp()) {
            return null;
        }

        return new AccessToken($value, CarbonImmutable::createFromTimestamp($expiresAt));
    }

    /**
     * @param list<OAuthScope> $scopes
     */
    private function refresh(string $key, string $emitterTaxId, array $scopes, string $endpoint): AccessToken
    {
        [$clientId, $clientSecret] = $this->credentials($emitterTaxId);
        $form                      = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->refreshToken($emitterTaxId),
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => self::scope($scopes),
        ];

        try {
            $response = $this->requests->plain($this->config->http->platform)->acceptJson()->asForm()->post($endpoint, $form);
        } catch (ConnectionException) {
            throw OAuthException::tokenUnavailable($emitterTaxId);
        }

        return $this->issue($key, $this->granted($emitterTaxId, $response));
    }

    /**
     * @return array{string, int}
     */
    private function granted(string $emitterTaxId, #[SensitiveParameter] Response $response): array
    {
        $status = $response->status();
        throw_if(\in_array($status, self::REFUSED_STATUSES, true), OAuthException::tokenRefused($emitterTaxId, $status));

        $unexpected = OAuthException::unexpectedResponse($emitterTaxId, $status);
        throw_unless($status === 200, $unexpected);

        try {
            $grant = json_decode($this->body->read($response, $emitterTaxId), true, Fiscal::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException|TransportException) {
            throw $unexpected;
        }

        $grant   = \is_array($grant) ? $grant : [];
        $rotated = $grant['refresh_token'] ?? null;
        throw_unless($rotated === null || self::isToken($rotated), $unexpected);
        if (\is_string($rotated)) {
            $this->refreshTokens->replace($emitterTaxId, $rotated);
        }

        $accessToken = $grant['access_token'] ?? null;
        $expiresIn   = $grant['expires_in'] ?? null;
        throw_unless(self::isToken($accessToken) && \is_int($expiresIn) && $expiresIn > 0, $unexpected);

        return [$accessToken, $expiresIn];
    }

    /**
     * @param array{string, int} $grant
     */
    private function issue(string $key, #[SensitiveParameter] array $grant): AccessToken
    {
        [$value, $expiresIn] = $grant;
        $lifetime            = $expiresIn - self::EXPIRY_MARGIN_SECONDS;
        if ($lifetime < 1) {
            return new AccessToken($value, $this->now()->addSeconds($expiresIn));
        }

        $token  = new AccessToken($value, $this->now()->addSeconds($lifetime));
        $stored = json_encode(['value' => $value, 'expires_at' => $token->expiresAt->getTimestamp()], JSON_THROW_ON_ERROR);
        $this->cache()->put($key, $this->encryption->encryptString($stored), $lifetime);

        return $token;
    }

    /**
     * @param list<OAuthScope> $scopes
     */
    private function key(string $emitterTaxId, array $scopes): string
    {
        $client = $this->config->transmitter->oauth->clientId ?? '';

        return $this->config->cache->prefix . ':oauth:' . hash('sha256', $client . '|' . $emitterTaxId . '|' . self::scope($scopes));
    }

    private function lockKey(string $emitterTaxId): string
    {
        $client = $this->config->transmitter->oauth->clientId ?? '';

        return $this->config->cache->prefix . ':oauth:lock:' . hash('sha256', $client . '|' . $emitterTaxId);
    }

    private function refreshToken(string $emitterTaxId): string
    {
        return $this->refreshTokens->find($emitterTaxId) ?? throw OAuthException::refreshTokenMissing($emitterTaxId);
    }

    /**
     * @return array{string, string}
     */
    private function credentials(string $emitterTaxId): array
    {
        $oauth = $this->config->transmitter->oauth;

        return [
            $oauth->clientId ?? throw self::missing($emitterTaxId, 'client_id'),
            $oauth->clientSecret() ?? throw self::missing($emitterTaxId, 'client_secret'),
        ];
    }

    /**
     * @phpstan-assert-if-true string $token
     */
    private static function isToken(#[SensitiveParameter] mixed $token): bool
    {
        return \is_string($token) && preg_match(self::B64_TOKEN, $token) === 1;
    }

    /**
     * @param list<OAuthScope> $scopes
     */
    private static function scope(array $scopes): string
    {
        return collect($scopes)->map(static fn (OAuthScope $scope): string => $scope->value)->unique()->sort()->implode(' ');
    }

    private static function missing(string $emitterTaxId, string $field): TransportException
    {
        return TransportException::because(TransportFailure::MissingCredentials, $emitterTaxId, field: 'efatura.transmitter.oauth.' . $field);
    }

    private function cache(): Repository
    {
        return $this->caches->store($this->config->cache->store);
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->clock->now());
    }
}
