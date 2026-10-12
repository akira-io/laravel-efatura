<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Enums\OAuthScope;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\OAuthException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\OAuth\AccessToken;
use Akira\Efatura\OAuth\RefreshTokenAccessTokenProvider;
use Akira\Efatura\Tests\Support\ExceptionTrace;
use Akira\Efatura\Tests\Support\InMemoryRefreshTokens;
use Akira\Efatura\Tests\Support\LocklessCacheStore;
use Akira\Efatura\Tests\Support\OAuthFixtures as O;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function oauthCacheKey(array $scopes): string
{
    $scope = collect($scopes)->map(fn (OAuthScope $scope): string => $scope->value)->unique()->sort()->implode(' ');

    return 'efatura:oauth:' . hash('sha256', O::CLIENT_ID . '|' . O::EMITTER . '|' . $scope);
}

function tokenProvider(): RefreshTokenAccessTokenProvider
{
    return resolve(RefreshTokenAccessTokenProvider::class);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-08T10:15:30Z');
    $this->secret        = 'client-secret-' . bin2hex(random_bytes(8));
    $this->refreshToken  = 'refresh-' . bin2hex(random_bytes(8));
    $this->accessToken   = 'access-' . bin2hex(random_bytes(8));
    $this->refreshTokens = new InMemoryRefreshTokens([O::EMITTER => $this->refreshToken]);
    $this->scopes        = [OAuthScope::DfeCreate, OAuthScope::DfeReadIud];
    O::configure($this->refreshTokens, $this->secret);
});

it('implements the access token provider contract', function (): void {
    expect(tokenProvider())->toBeInstanceOf(AccessTokenProvider::class);
});

it('exchanges the stored refresh token for an access token with the transmitter credentials', function (): void {
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => O::grant($this->accessToken)]);

    $token = tokenProvider()->token(O::EMITTER, [OAuthScope::DfeReadIud, OAuthScope::DfeCreate, OAuthScope::DfeCreate]);

    expect($token->value)->toBe($this->accessToken)
        ->and($token->expiresAt->toIso8601ZuluString())->toBe('2026-02-08T10:19:30Z')
        ->and($this->refreshTokens->replaced)->toBe([]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === O::TOKEN_URL
        && $request->isForm()
        && $request->data() === [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'client_id'     => O::CLIENT_ID,
            'client_secret' => $this->secret,
            'scope'         => 'cv_ef_dfe_create cv_ef_dfe_read_iud',
        ]);
});

it('caches the access token encrypted until a minute before it expires', function (): void {
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => Http::sequence()->pushResponse(O::grant($this->accessToken))->pushResponse(O::grant('renewed'))]);

    $first  = tokenProvider()->token(O::EMITTER, $this->scopes);
    $stored = Cache::store('array')->get(oauthCacheKey($this->scopes));
    CarbonImmutable::setTestNow('2026-02-08T10:19:29Z');
    $reused = tokenProvider()->token(O::EMITTER, $this->scopes);
    CarbonImmutable::setTestNow('2026-02-08T10:19:30Z');
    $renewed = tokenProvider()->token(O::EMITTER, $this->scopes);

    expect($stored)->toBeString()->not->toContain($this->accessToken)
        ->and($reused->value)->toBe($first->value)
        ->and($renewed->value)->toBe('renewed');
    Http::assertSentCount(3);
});

it('keeps tokens apart per emitter and per set of scopes', function (): void {
    $this->refreshTokens->replace('200300400', 'other-refresh');
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => Http::sequence()->pushResponse(O::grant('a'))->pushResponse(O::grant('b'))->pushResponse(O::grant('c'))]);

    $tokens = [
        tokenProvider()->token(O::EMITTER, $this->scopes)->value,
        tokenProvider()->token('200300400', $this->scopes)->value,
        tokenProvider()->token(O::EMITTER, [OAuthScope::EventCreate])->value,
        tokenProvider()->token(O::EMITTER, array_reverse($this->scopes))->value,
    ];

    expect($tokens)->toBe(['a', 'b', 'c', 'a']);
});

it('hands a rotated refresh token back to the application', function (): void {
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => O::grant($this->accessToken, grant: ['refresh_token' => 'rotated-refresh'])]);

    tokenProvider()->token(O::EMITTER, $this->scopes);

    expect($this->refreshTokens->replaced)->toBe([[O::EMITTER, 'rotated-refresh']])
        ->and($this->refreshTokens->find(O::EMITTER))->toBe('rotated-refresh');
});

it('renews a token it was told to forget', function (): void {
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => Http::sequence()->pushResponse(O::grant('first'))->pushResponse(O::grant('second'))]);

    tokenProvider()->token(O::EMITTER, $this->scopes);
    tokenProvider()->forget(O::EMITTER, $this->scopes);

    expect(tokenProvider()->token(O::EMITTER, $this->scopes)->value)->toBe('second');
});

it('does not cache a token that expires within the margin', function (): void {
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => Http::sequence()->pushResponse(O::grant('short', 60))->pushResponse(O::grant('next', 60))]);

    $first = tokenProvider()->token(O::EMITTER, $this->scopes);

    expect($first->expiresAt->toIso8601ZuluString())->toBe('2026-02-08T10:16:30Z')
        ->and(Cache::store('array')->get(oauthCacheKey($this->scopes)))->toBeNull()
        ->and(tokenProvider()->token(O::EMITTER, $this->scopes)->value)->toBe('next');
});

it('ignores a cached entry it cannot read', function (string $stored): void {
    Cache::store('array')->put(oauthCacheKey($this->scopes), $stored === 'undecryptable' ? $stored : encrypt($stored, false), 300);
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => O::grant($this->accessToken)]);

    expect(tokenProvider()->token(O::EMITTER, $this->scopes)->value)->toBe($this->accessToken);
})->with([
    'undecryptable'    => ['undecryptable'],
    'not json'         => ['not json'],
    'a json string'    => ['"token"'],
    'no expiry'        => ['{"value":"stale"}'],
    'an expired token' => ['{"value":"stale","expires_at":1}'],
]);

it('renews without a lock on a store that has none', function (): void {
    Cache::extend('lockless', fn (): Repository => Cache::repository(new LocklessCacheStore));
    config()->set('cache.stores.lockless', ['driver' => 'lockless']);
    O::configure($this->refreshTokens, $this->secret, ['cache' => ['store' => 'lockless']]);
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => O::grant($this->accessToken)]);

    expect(tokenProvider()->token(O::EMITTER, $this->scopes)->value)->toBe($this->accessToken)
        ->and(tokenProvider()->token(O::EMITTER, $this->scopes)->value)->toBe($this->accessToken);
    Http::assertSentCount(2);
});

it('never retries the token request', function (): void {
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => Http::sequence()->push('', 503)->pushResponse(O::grant($this->accessToken))]);

    expect(fn (): AccessToken => tokenProvider()->token(O::EMITTER, $this->scopes))->toThrow(OAuthException::class, 'oauth.unexpected_response');
    Http::assertSentCount(2);
});

it('fails without leaking any secret', function (Closure $response, string $errorCode, array $context): void {
    $original = ini_set('zend.exception_ignore_args', '0');
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => $response]);

    try {
        tokenProvider()->token(O::EMITTER, $this->scopes);
    } catch (EfaturaException $efaturaException) {
        expect($efaturaException->errorCode)->toBe($errorCode)
            ->and($efaturaException->context)->toBe($context)
            ->and(ExceptionTrace::exposed($efaturaException))->not->toContain($this->secret)
            ->not->toContain($this->refreshToken)
            ->not->toContain($this->accessToken);
    } finally {
        ini_set('zend.exception_ignore_args', $original);
    }

    expect($efaturaException ?? null)->toBeInstanceOf(EfaturaException::class);
})->with([
    'invalid grant'          => [fn (): PromiseInterface => Http::response(['error' => 'invalid_grant'], 400), 'oauth.token_refused', ['emitter' => O::EMITTER, 'status' => 400]],
    'unauthorized client'    => [fn (): PromiseInterface => Http::response('', 401), 'oauth.token_refused', ['emitter' => O::EMITTER, 'status' => 401]],
    'no access token'        => [fn (): PromiseInterface => Http::response(['expires_in' => 300]), 'oauth.unexpected_response', ['emitter' => O::EMITTER, 'status' => 200]],
    'an empty access token'  => [fn (): PromiseInterface => O::grant(''), 'oauth.unexpected_response', ['emitter' => O::EMITTER, 'status' => 200]],
    'a text expiry'          => [fn (): PromiseInterface => Http::response(['access_token' => 'x', 'expires_in' => '300']), 'oauth.unexpected_response', ['emitter' => O::EMITTER, 'status' => 200]],
    'a zero expiry'          => [fn (): PromiseInterface => O::grant('x', 0), 'oauth.unexpected_response', ['emitter' => O::EMITTER, 'status' => 200]],
    'an empty refresh token' => [fn (): PromiseInterface => O::grant('x', grant: ['refresh_token' => '']), 'oauth.unexpected_response', ['emitter' => O::EMITTER, 'status' => 200]],
    'html'                   => [fn (): PromiseInterface => Http::response('<html/>'), 'oauth.unexpected_response', ['emitter' => O::EMITTER, 'status' => 200]],
    'a json list'            => [fn (): PromiseInterface => Http::response('[1]'), 'oauth.unexpected_response', ['emitter' => O::EMITTER, 'status' => 200]],
    'a server error'         => [fn (): PromiseInterface => Http::response('', 500), 'oauth.unexpected_response', ['emitter' => O::EMITTER, 'status' => 500]],
    'a refused connection'   => [
        fn (Request $request): PromiseInterface => Create::rejectionFor(new ConnectException('cURL error 7: Failed to connect', $request->toPsrRequest())),
        'oauth.token_unavailable',
        ['emitter' => O::EMITTER],
    ],
    'a silent token endpoint' => [
        fn (Request $request): PromiseInterface => Create::rejectionFor(new RequestException('cURL error 28: Operation timed out', $request->toPsrRequest())),
        'oauth.token_unavailable',
        ['emitter' => O::EMITTER],
    ],
    'an access token outside the bearer syntax' => [fn (): PromiseInterface => O::grant('acc ess'), 'oauth.unexpected_response', ['emitter' => O::EMITTER, 'status' => 200]],
    'a refresh token outside the bearer syntax' => [
        fn (): PromiseInterface => O::grant('x', grant: ['refresh_token' => "rotated\nrefresh"]),
        'oauth.unexpected_response',
        ['emitter' => O::EMITTER, 'status' => 200],
    ],
]);

it('asks nothing of the issuer without a refresh token for the emitter', function (): void {
    Http::fake();

    expect(fn (): AccessToken => tokenProvider()->token('900800700', $this->scopes))->toThrow(function (OAuthException $exception): void {
        expect($exception->errorCode)->toBe('oauth.refresh_token_missing')
            ->and($exception->context)->toBe(['emitter' => '900800700']);
    });
    Http::assertNothingSent();
});

it('asks nothing of the issuer without the transmitter client credentials', function (string $missing): void {
    O::configure($this->refreshTokens, $this->secret, ['transmitter' => ['oauth' => [$missing => null]]]);
    Http::fake();

    expect(fn (): AccessToken => tokenProvider()->token(O::EMITTER, $this->scopes))->toThrow(function (TransportException $exception) use ($missing): void {
        expect($exception->failure)->toBe(TransportFailure::MissingCredentials)
            ->and($exception->field)->toBe('efatura.transmitter.oauth.' . $missing);
    });
    Http::assertNothingSent();
})->with(['client_id', 'client_secret']);

it('keeps a rotated refresh token even when the rest of the grant is unusable', function (): void {
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => Http::response(['refresh_token' => 'rotated-refresh', 'expires_in' => 300])]);

    expect(fn (): AccessToken => tokenProvider()->token(O::EMITTER, $this->scopes))->toThrow(OAuthException::class, 'oauth.unexpected_response')
        ->and($this->refreshTokens->replaced)->toBe([[O::EMITTER, 'rotated-refresh']]);
});

it('keeps the stored refresh token when the rotated one is outside the bearer syntax', function (): void {
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => O::grant($this->accessToken, grant: ['refresh_token' => 'rotated refresh'])]);

    expect(fn (): AccessToken => tokenProvider()->token(O::EMITTER, $this->scopes))->toThrow(OAuthException::class, 'oauth.unexpected_response')
        ->and($this->refreshTokens->find(O::EMITTER))->toBe($this->refreshToken);
});

it('serializes renewals per emitter whatever the scopes, and lets other emitters through', function (): void {
    Sleep::fake(syncWithCarbon: true);
    O::configure($this->refreshTokens, $this->secret, ['http' => ['timeout_seconds' => 1, 'connect_timeout_seconds' => 1]]);
    $this->refreshTokens->replace('200300400', 'other-refresh');
    $this->nested = [];
    Http::fake([O::DISCOVERY_URL => O::discovery(), O::TOKEN_URL => function (Request $request): PromiseInterface {
        if ($request['refresh_token'] === $this->refreshToken) {
            $locks              = Cache::store(resolve(EfaturaConfig::class)->cache->store)->getStore()->locks;
            $this->lockLifetime = CarbonImmutable::now()->diffInSeconds(collect($locks)->first()['expiresAt']);

            foreach (['same emitter' => O::EMITTER, 'other emitter' => '200300400'] as $label => $emitter) {
                try {
                    $this->nested[$label] = tokenProvider()->token($emitter, [OAuthScope::EventCreate])->value;
                } catch (OAuthException $oauthException) {
                    $this->nested[$label] = $oauthException->errorCode;
                }
            }
        }

        return O::grant('granted-' . $request['refresh_token']);
    }]);

    expect(tokenProvider()->token(O::EMITTER, $this->scopes)->value)->toBe('granted-' . $this->refreshToken)
        ->and($this->nested)->toBe(['same emitter' => 'oauth.token_unavailable', 'other emitter' => 'granted-other-refresh'])
        ->and($this->lockLifetime)->toBe(12.0);
    Sleep::assertSleptTimes(7);
    Sleep::assertSequence(array_fill(0, 7, Sleep::for(250)->milliseconds()));
});
