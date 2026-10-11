<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\RefreshTokenRepository;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\EfaturaServiceProvider;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\OAuth\RefreshTokenAccessTokenProvider;
use Akira\Efatura\Tests\Support\ComposerMetadata;
use Akira\Efatura\Tests\Support\InMemoryRefreshTokens;
use Akira\Efatura\Tests\Support\RecordingAccessTokens;

it('loads the immutable configuration once when first resolved', function (): void {
    config()->set('efatura.environment', 'HOMOLOGATION');
    config()->set('efatura.emitter.name', 'First emitter');

    $first = resolve(EfaturaConfig::class);

    config()->set('efatura.environment', 'PRODUCTION');
    config()->set('efatura.emitter.name', 'Second emitter');

    expect($first)->toBe(resolve(EfaturaConfig::class))
        ->and($first->environment->environment)->toBe(Environment::Homologation)
        ->and($first->emitter->name)->toBe('First emitter');
});

it('shares one manager with the resolved configuration', function (): void {
    $manager = resolve(EfaturaManager::class);

    expect($manager)->toBe(resolve(EfaturaManager::class))
        ->and($manager->config())->toBe(resolve(EfaturaConfig::class));
});

it('declares the provider metadata and registers the DFA views', function (): void {
    expect(ComposerMetadata::read()['extra']['laravel']['providers'])->toContain(EfaturaServiceProvider::class)
        ->and(app()->getLoadedProviders())->toHaveKey(EfaturaServiceProvider::class)
        ->and(resolve('view')->getFinder()->getHints())->toHaveKey('efatura');
});

it('binds the access token provider only once the application binds a refresh token repository', function (): void {
    $provider = app()->getProvider(EfaturaServiceProvider::class);
    $provider->packageBooted();

    $unbound = app()->bound(AccessTokenProvider::class);

    config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    app()->instance(RefreshTokenRepository::class, new InMemoryRefreshTokens);
    $provider->packageBooted();

    expect($unbound)->toBeFalse()
        ->and(resolve(AccessTokenProvider::class))->toBeInstanceOf(RefreshTokenAccessTokenProvider::class);
});

it('keeps an access token provider the application binds itself', function (): void {
    $tokens = new RecordingAccessTokens('provider');
    app()->instance(AccessTokenProvider::class, $tokens);
    app()->instance(RefreshTokenRepository::class, new InMemoryRefreshTokens);

    app()->getProvider(EfaturaServiceProvider::class)->packageBooted();

    expect(resolve(AccessTokenProvider::class))->toBe($tokens);
});
