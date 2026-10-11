<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Enums\ArtifactKind;
use Akira\Efatura\Exceptions\StorageException;
use Akira\Efatura\Storage\FilesystemArtifactStore;
use Akira\Efatura\Storage\StoredArtifact;
use Akira\Efatura\Tests\Support\LocklessCacheStore;
use Akira\Efatura\Tests\Support\StorageFixtures as S;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('archive');
    config()->set('efatura.storage.disk', 'archive');
});

function artifactLockKey(ArtifactKind $kind, string $prefix = 'efatura'): string
{
    return $prefix . ':artifact:' . hash('sha256', 'archive:efatura/3/100200300/2026/10/' . S::iud() . '/' . $kind->fileName(S::iud()));
}

function quickStore(): FilesystemArtifactStore
{
    return app()->make(FilesystemArtifactStore::class, ['lockWaitSeconds' => 0]);
}

it('writes under a lock on the path and releases it', function (string $store): void {
    $directory = sys_get_temp_dir() . '/efatura-locks-' . bin2hex(random_bytes(6));
    config()->set('efatura.cache.store', $store);
    config()->set('cache.stores.file.path', $directory);

    $lock = Cache::store($store)->lock(artifactLockKey(ArtifactKind::Archive), 5);

    try {
        expect(quickStore()->put(S::iud(), ArtifactKind::Archive, 'zip')->path)->toEndWith('.zip')
            ->and(resolve(EfaturaConfig::class)->cache->store)->toBe($store)
            ->and($lock->get())->toBeTrue();
    } finally {
        $lock->release();
        new Filesystem()->deleteDirectory($directory);
    }
})->with(['array', 'file']);

it('reports a path another process is storing as retryable and writes nothing', function (): void {
    config()->set('efatura.cache.store', 'array');
    config()->set('efatura.cache.prefix', 'fiscal');

    $held = Cache::store('array')->lock(artifactLockKey(ArtifactKind::Archive, 'fiscal'), 30);
    $held->get();

    expect(fn (): StoredArtifact => quickStore()->put(S::iud(), ArtifactKind::Archive, 'zip'))
        ->toThrow(function (StorageException $exception): void {
            expect($exception->errorCode)->toBe('storage.unavailable')
                ->and($exception->context)->toBe(['path' => 'efatura/3/100200300/2026/10/' . S::iud() . '/' . S::iud() . '.zip'])
                ->and($exception->retryable)->toBeTrue()
                ->and($exception->getPrevious())->toBeInstanceOf(LockTimeoutException::class);
        })
        ->and(Storage::disk('archive')->allFiles())->toBe([])
        ->and(quickStore()->put(S::iud(), ArtifactKind::SignedXml, '<Dfe/>')->kind)->toBe(ArtifactKind::SignedXml);

    $held->release();
});

it('stores without a lock on a cache store that has none', function (): void {
    Cache::extend('lockless', fn (): Repository => Cache::repository(new LocklessCacheStore));
    config()->set('cache.stores.lockless', ['driver' => 'lockless']);
    config()->set('efatura.cache.store', 'lockless');

    expect(quickStore()->put(S::iud(), ArtifactKind::Archive, 'zip')->path)->toEndWith('.zip');
});

it('reports a cache store that cannot lock as retryable and writes nothing', function (): void {
    config()->set('cache.stores.broken-locks', ['driver' => 'database', 'connection' => 'testing', 'table' => 'missing_cache', 'lock_table' => 'missing_cache_locks']);
    config()->set('efatura.cache.store', 'broken-locks');

    expect(fn (): StoredArtifact => quickStore()->put(S::iud(), ArtifactKind::Archive, 'zip'))
        ->toThrow(function (StorageException $exception): void {
            expect($exception->errorCode)->toBe('storage.unavailable')
                ->and($exception->retryable)->toBeTrue()
                ->and($exception->getPrevious())->toBeInstanceOf(QueryException::class);
        })
        ->and(Storage::disk('archive')->allFiles())->toBe([]);
});

it('keeps the error of the store itself when the lock is held', function (): void {
    config()->set('efatura.cache.store', 'array');
    quickStore()->put(S::iud(), ArtifactKind::Archive, 'zip');

    expect(fn (): StoredArtifact => quickStore()->put(S::iud(), ArtifactKind::Archive, 'another zip'))
        ->toThrow(function (StorageException $exception): void {
            expect($exception->errorCode)->toBe('storage.conflict');
        })
        ->and(Cache::store('array')->lock(artifactLockKey(ArtifactKind::Archive), 5)->get())->toBeTrue();
});
