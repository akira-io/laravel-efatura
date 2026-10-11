<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Tests\Support\ConfigFixtures;
use Akira\Efatura\Tests\Support\RecordingAccessTokens;
use Akira\Efatura\Transport\MiddlewareTransport;
use Akira\Efatura\Transport\PlatformTransport;

beforeEach(function (): void {
    $this->singleton    = resolve(EfaturaManager::class);
    $this->default      = $this->singleton->config();
    $this->firstConfig  = ConfigFixtures::load(['emitter' => ['name' => 'First emitter'], 'storage' => ['disk' => 'first-disk']]);
    $this->secondConfig = ConfigFixtures::load(['emitter' => ['name' => 'Second emitter'], 'storage' => ['disk' => 'second-disk']]);
});

it('isolates each scoped manager from the singleton', function (): void {
    $first  = $this->singleton->withConfig($this->firstConfig);
    $second = $this->singleton->withConfig($this->secondConfig);

    expect($first)->not->toBe($this->singleton)
        ->and($second)->not->toBe($this->singleton)
        ->and($first)->not->toBe($second)
        ->and($first->config())->toBe($this->firstConfig)
        ->and($second->config())->toBe($this->secondConfig)
        ->and($this->singleton->config())->toBe($this->default)
        ->and($first->config()->emitter->name)->toBe('First emitter')
        ->and($second->config()->emitter->name)->toBe('Second emitter')
        ->and($first->config()->storage->disk)->toBe('first-disk')
        ->and($second->config()->storage->disk)->toBe('second-disk')
        ->and($this->singleton->config()->emitter)->toBeNull()
        ->and($this->singleton->config()->storage->disk)->toBe($this->default->storage->disk)
        ->and(resolve(EfaturaManager::class))->toBe($this->singleton);
});

it('gives each scoped manager its own memoized fluent entry bound to its configuration', function (): void {
    $first  = $this->singleton->withConfig($this->firstConfig);
    $second = $this->singleton->withConfig($this->secondConfig);

    expect($first->efatura())->toBe($first->efatura())
        ->and($second->efatura())->not->toBe($first->efatura())
        ->and($first->efatura()->config())->toBe($this->firstConfig)
        ->and($second->efatura()->config())->toBe($this->secondConfig);
});

it('returns the transport of the configured or named driver', function (?string $name, string $transport): void {
    app()->instance(AccessTokenProvider::class, new RecordingAccessTokens('manager'));

    expect($this->singleton->driver($name))->toBeInstanceOf($transport);
})->with([
    'the configured driver' => [null, MiddlewareTransport::class],
    'the platform'          => ['platform', PlatformTransport::class],
    'any letter case'       => ['MIDDLEWARE', MiddlewareTransport::class],
]);

it('refuses a driver name it does not know', function (): void {
    expect(fn (): Transport => $this->singleton->driver('smtp'))->toThrow(DefinitionException::class, 'smtp is not a transport driver; use middleware or platform.');
});

it('resolves the driver of a scoped manager from its own configuration', function (): void {
    app()->instance(AccessTokenProvider::class, new RecordingAccessTokens('manager'));

    expect($this->singleton->withConfig(ConfigFixtures::load(['driver' => 'platform']))->driver())->toBeInstanceOf(PlatformTransport::class);
});
