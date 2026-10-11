<?php

declare(strict_types=1);

namespace Akira\Efatura\Storage;

use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Configuration\CacheConfig;
use Akira\Efatura\Configuration\StorageConfig;
use Akira\Efatura\Contracts\ArtifactStore;
use Akira\Efatura\Enums\ArtifactKind;
use Akira\Efatura\Exceptions\StorageException;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class FilesystemArtifactStore implements ArtifactStore
{
    private const string CHECKSUM = 'sha256';

    private const int LOCK_SECONDS = 60;

    public function __construct(
        private Factory $disks,
        private StorageConfig $config,
        private ParseIudAction $parseIud,
        private CacheFactory $caches,
        private CacheConfig $cache,
        private int $lockWaitSeconds = 10,
    ) {}

    public function put(string $iud, ArtifactKind $kind, string $contents): StoredArtifact
    {
        $path     = $this->path($iud, $kind);
        $disk     = $this->privateDisk();
        $artifact = new StoredArtifact($iud, $kind, $this->config->disk, $path, \strlen($contents), hash(self::CHECKSUM, $contents));

        return $this->locked($path, fn (): StoredArtifact => $this->store($disk, $artifact, $contents));
    }

    public function get(StoredArtifact $artifact): string
    {
        $path = $this->path($artifact->iud, $artifact->kind);
        $disk = $this->disk();

        if ($artifact->disk !== $this->config->disk || $artifact->path !== $path || ! $this->exists($disk, $path)) {
            throw StorageException::missing($artifact->path);
        }

        $contents = $this->read($disk, $path);

        return hash_equals($artifact->checksum, hash(self::CHECKSUM, $contents)) ? $contents : throw StorageException::corrupted($path);
    }

    /**
     * @param Closure(): StoredArtifact $store
     */
    private function locked(string $path, Closure $store): StoredArtifact
    {
        $locks = $this->caches->store($this->cache->store)->getStore();

        if (! $locks instanceof LockProvider) {
            return $store();
        }

        $lock = $locks->lock($this->cache->prefix . ':artifact:' . hash(self::CHECKSUM, $this->config->disk . ':' . $path), self::LOCK_SECONDS);

        try {
            $lock->block($this->lockWaitSeconds);
        } catch (Throwable $throwable) {
            throw StorageException::unavailable($path, $throwable);
        }

        try {
            return $store();
        } finally {
            $lock->release();
        }
    }

    private function store(Filesystem $disk, StoredArtifact $artifact, string $contents): StoredArtifact
    {
        if (! $this->exists($disk, $artifact->path)) {
            $this->write($disk, $artifact->path, $contents);

            return $artifact;
        }

        return hash_equals($artifact->checksum, hash(self::CHECKSUM, $this->read($disk, $artifact->path)))
            ? $artifact
            : throw StorageException::conflict($artifact->path);
    }

    private function path(string $iud, ArtifactKind $kind): string
    {
        try {
            $parsed = $this->parseIud->handle($iud);
        } catch (ValidationException) {
            throw StorageException::invalidIud();
        }

        return implode('/', [
            $this->config->path,
            $parsed->repository->code(),
            $parsed->emitterTaxId,
            $parsed->issueDate->format('Y'),
            $parsed->issueDate->format('m'),
            $iud,
            $kind->fileName($iud),
        ]);
    }

    private function privateDisk(): Filesystem
    {
        $disk = $this->disk();

        if ($disk instanceof FilesystemAdapter && ($disk->getConfig()['visibility'] ?? null) === Filesystem::VISIBILITY_PUBLIC) {
            throw StorageException::publicDisk($this->config->disk);
        }

        return $disk;
    }

    private function disk(): Filesystem
    {
        try {
            return $this->disks->disk($this->config->disk);
        } catch (Throwable $throwable) {
            throw StorageException::diskInvalid($this->config->disk, $throwable);
        }
    }

    private function exists(Filesystem $disk, string $path): bool
    {
        try {
            return $disk->exists($path);
        } catch (Throwable $throwable) {
            throw StorageException::unavailable($path, $throwable);
        }
    }

    private function read(Filesystem $disk, string $path): string
    {
        try {
            $contents = $disk->get($path);
        } catch (Throwable $throwable) {
            throw StorageException::unavailable($path, $throwable);
        }

        return $contents ?? throw StorageException::unavailable($path);
    }

    private function write(Filesystem $disk, string $path, string $contents): void
    {
        try {
            $written = $disk->put($path, $contents);
        } catch (Throwable $throwable) {
            throw StorageException::unavailable($path, $throwable);
        }

        if (! $written) {
            throw StorageException::unavailable($path);
        }
    }
}
