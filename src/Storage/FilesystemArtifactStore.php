<?php

declare(strict_types=1);

namespace Akira\Efatura\Storage;

use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Configuration\StorageConfig;
use Akira\Efatura\Contracts\ArtifactStore;
use Akira\Efatura\Enums\ArtifactKind;
use Akira\Efatura\Exceptions\StorageException;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class FilesystemArtifactStore implements ArtifactStore
{
    private const string CHECKSUM = 'sha256';

    public function __construct(private Factory $disks, private StorageConfig $config, private ParseIudAction $parseIud) {}

    public function put(string $iud, ArtifactKind $kind, string $contents): StoredArtifact
    {
        $path     = $this->path($iud, $kind);
        $disk     = $this->disk();
        $artifact = new StoredArtifact($iud, $kind, $this->config->disk, $path, \strlen($contents), hash(self::CHECKSUM, $contents));

        if (! $this->exists($disk, $path)) {
            $this->write($disk, $path, $contents);

            return $artifact;
        }

        return hash_equals($artifact->checksum, hash(self::CHECKSUM, $this->read($disk, $path)))
            ? $artifact
            : throw StorageException::conflict($path);
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
