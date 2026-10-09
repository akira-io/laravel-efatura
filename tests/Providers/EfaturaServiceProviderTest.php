<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\EfaturaServiceProvider;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Tests\Support\ComposerMetadata;

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

it('declares the provider metadata and registers without absent views', function (): void {
    expect(ComposerMetadata::read()['extra']['laravel']['providers'])->toContain(EfaturaServiceProvider::class)
        ->and(app()->getLoadedProviders())->toHaveKey(EfaturaServiceProvider::class)
        ->and(resolve('view')->getFinder()->getHints())->not->toHaveKey('efatura');
});
