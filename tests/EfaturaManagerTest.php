<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Efatura;
use Akira\Efatura\EfaturaManager;
use Illuminate\Config\Repository;

it('isolates each scoped manager and its fluent entry from the singleton', function (): void {
    $singleton   = resolve(EfaturaManager::class);
    $default     = $singleton->config();
    $firstConfig = (new LoadEfaturaConfig(new Repository([
        'efatura'     => ['emitter' => ['name' => 'First emitter'], 'storage' => ['disk' => 'first-disk']],
        'filesystems' => ['default' => 'host-disk'],
        'cache'       => ['default' => 'host-cache'],
        'database'    => ['default' => 'host-database'],
        'queue'       => ['default' => 'host-queue'],
    ])))();
    $secondConfig = (new LoadEfaturaConfig(new Repository([
        'efatura'     => ['emitter' => ['name' => 'Second emitter'], 'storage' => ['disk' => 'second-disk']],
        'filesystems' => ['default' => 'host-disk'],
        'cache'       => ['default' => 'host-cache'],
        'database'    => ['default' => 'host-database'],
        'queue'       => ['default' => 'host-queue'],
    ])))();

    $first  = $singleton->withConfig($firstConfig);
    $second = $singleton->withConfig($secondConfig);

    expect($first)->not->toBe($singleton)
        ->and($second)->not->toBe($singleton)
        ->and($first)->not->toBe($second)
        ->and($first->config())->toBe($firstConfig)
        ->and($second->config())->toBe($secondConfig)
        ->and($singleton->config())->toBe($default)
        ->and($first->config()->emitter->name)->toBe('First emitter')
        ->and($second->config()->emitter->name)->toBe('Second emitter')
        ->and($first->config()->storage->disk)->toBe('first-disk')
        ->and($second->config()->storage->disk)->toBe('second-disk')
        ->and($singleton->config()->emitter)->toBeNull()
        ->and($singleton->config()->storage->disk)->toBe($default->storage->disk)
        ->and(resolve(EfaturaManager::class))->toBe($singleton);

    expect($first->efatura())->toBeInstanceOf(Efatura::class)
        ->and($first->efatura())->toBe($first->efatura())
        ->and($second->efatura())->not->toBe($first->efatura())
        ->and($first->efatura()->config())->toBe($firstConfig)
        ->and($second->efatura()->config())->toBe($secondConfig);
});
