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
use Akira\Efatura\Transport\BoundedBody;
use Akira\Efatura\Transport\FailureClassifier;
use Akira\Efatura\Transport\TransportRequests;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
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
    private const int EXPIRY_MARGIN_SECONDS = 60;

    private const int JSON_DEPTH = 8;

    private const array REFUSED_STATUSES = [400, 401];

    public function __construct(
        private TransportRequests $requests,
        private FailureClassifier $failures,
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

        return $this->cached($key) ?? $this->locked($key, fn (): AccessToken => $this->renew($key, $emitterTaxId, $scopes));
    }

    public function forget(string $emitterTaxId, array $scopes): void
    {
        $this->cache()->forget($this->key($emitterTaxId, $scopes));
    }

    /**
     * @param Closure(): AccessToken $refresh
     */
    private function locked(string $key, #[SensitiveParameter] Closure $refresh): AccessToken
    {
        $store = $this->cache()->getStore();
        if (! $store instanceof LockProvider) {
            return $refresh();
        }

        $seconds = $this->config->http->platform->timeoutSeconds + $this->config->http->platform->connectTimeoutSeconds;
        $token   = $store->lock($key . ':lock', $seconds)->block($seconds, $refresh);

        return $token instanceof AccessToken ? $token : $refresh();
    }

    /**
     * @param list<OAuthScope> $scopes
     */
    private function renew(string $key, string $emitterTaxId, array $scopes): AccessToken
    {
        return $this->cached($key) ?? $this->refresh($key, $emitterTaxId, $scopes);
    }

    private function cached(string $key): ?AccessToken
    {
        $payload = $this->cache()->get($key);

        try {
            $stored = \is_string($payload) ? json_decode($this->encryption->decryptString($payload), true, self::JSON_DEPTH, JSON_THROW_ON_ERROR) : null;
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
    private function refresh(string $key, string $emitterTaxId, array $scopes): AccessToken
    {
        $refreshToken = $this->refreshTokens->find($emitterTaxId) ?? throw OAuthException::refreshTokenMissing($emitterTaxId);
        $oauth        = $this->config->transmitter->oauth;
        $form         = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => $oauth->clientId ?? throw self::missing($emitterTaxId, 'client_id'),
            'client_secret' => $oauth->clientSecret ?? throw self::missing($emitterTaxId, 'client_secret'),
            'scope'         => self::scope($scopes),
        ];
        $endpoint = $this->discovery->tokenEndpoint();

        try {
            $response = $this->requests->plain($this->config->http->platform)->acceptJson()->asForm()->post($endpoint, $form);
        } catch (ConnectionException $connectionException) {
            throw $this->failures->classify($connectionException, $emitterTaxId);
        }

        return $this->issue($key, $emitterTaxId, $this->granted($emitterTaxId, $response));
    }

    /**
     * @return array{string, int, ?string}
     */
    private function granted(string $emitterTaxId, #[SensitiveParameter] Response $response): array
    {
        $status = $response->status();
        throw_if(\in_array($status, self::REFUSED_STATUSES, true), OAuthException::tokenRefused($emitterTaxId, $status));

        $unexpected = OAuthException::unexpectedResponse($emitterTaxId, $status);
        throw_unless($status === 200, $unexpected);

        try {
            $grant = json_decode($this->body->read($response, $emitterTaxId), true, self::JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException|TransportException) {
            throw $unexpected;
        }

        $accessToken = \is_array($grant) ? ($grant['access_token'] ?? null) : null;
        $expiresIn   = \is_array($grant) ? ($grant['expires_in'] ?? null) : null;
        $rotated     = \is_array($grant) ? ($grant['refresh_token'] ?? null) : null;
        throw_unless(\is_string($accessToken) && $accessToken !== '' && \is_int($expiresIn) && $expiresIn > 0, $unexpected);
        throw_unless($rotated === null || (\is_string($rotated) && $rotated !== ''), $unexpected);

        return [$accessToken, $expiresIn, $rotated];
    }

    /**
     * @param array{string, int, ?string} $grant
     */
    private function issue(string $key, string $emitterTaxId, #[SensitiveParameter] array $grant): AccessToken
    {
        [$value, $expiresIn, $rotated] = $grant;
        if ($rotated !== null) {
            $this->refreshTokens->replace($emitterTaxId, $rotated);
        }

        $lifetime = $expiresIn - self::EXPIRY_MARGIN_SECONDS;
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
