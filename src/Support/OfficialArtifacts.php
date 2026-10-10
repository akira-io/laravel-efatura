<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Exceptions\OfficialArtifactException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SensitiveParameter;
use Throwable;

use const JSON_THROW_ON_ERROR;

final readonly class OfficialArtifacts
{
    private string $root;

    /** @var array<array-key, mixed> */
    private array $files;

    /** @var array<array-key, mixed> */
    private array $profiles;

    public function __construct(private Filesystem $filesystem, #[SensitiveParameter] string $resources = __DIR__ . '/../../resources')
    {
        try {
            $root = realpath($resources);
        } catch (Throwable) {
            throw new OfficialArtifactException('artifacts.missing_root', 'load_manifest');
        }

        if ($root === false) {
            throw new OfficialArtifactException('artifacts.missing_root', 'load_manifest');
        }

        try {
            $isDirectory = $this->filesystem->isDirectory($root);
        } catch (Throwable) {
            throw new OfficialArtifactException('artifacts.missing_root', 'load_manifest');
        }

        if (! $isDirectory) {
            throw new OfficialArtifactException('artifacts.missing_root', 'load_manifest');
        }

        $this->root   = $root;
        $manifestPath = $this->checkedPath('official-artifacts.json');

        try {
            $manifest = $this->filesystem->json($manifestPath, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new OfficialArtifactException('artifacts.invalid_manifest', 'load_manifest');
        }

        if (! \is_array($manifest['files'] ?? null) || ! \is_array($manifest['signature_profiles'] ?? null)) {
            throw new OfficialArtifactException('artifacts.invalid_manifest', 'load_manifest');
        }

        $this->files    = $manifest['files'];
        $this->profiles = $manifest['signature_profiles'];
    }

    public function xsdEntry(#[SensitiveParameter] string $signatureProfile): string
    {
        $artifact = $this->profiles[$signatureProfile] ?? null;
        if (! \is_string($artifact)) {
            throw new OfficialArtifactException('artifacts.unknown_profile', 'xsd_entry');
        }

        return $this->path($artifact);
    }

    public function path(#[SensitiveParameter] string $artifact): string
    {
        if (preg_match('~[\\\:\x00-\x1f]|(?:^|/)(?:\.{1,2})?(?:/|$)~', $artifact) === 1 || ! Arr::exists($this->files, $artifact)) {
            throw new OfficialArtifactException('artifacts.unknown_or_unsafe_path', 'resolve');
        }

        $record = $this->files[$artifact];
        if (! \is_array($record) || ! \is_string($record['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $record['sha256']) !== 1) {
            throw new OfficialArtifactException('artifacts.invalid_checksum', 'resolve');
        }

        if (! \is_int($record['size'] ?? null) || $record['size'] < 0) {
            throw new OfficialArtifactException('artifacts.invalid_size', 'resolve');
        }

        $path = $this->checkedPath($artifact);

        try {
            $size = $this->filesystem->size($path);
        } catch (Throwable) {
            throw new OfficialArtifactException('artifacts.missing_or_unreadable', 'resolve');
        }

        if ($size !== $record['size']) {
            throw new OfficialArtifactException('artifacts.size_mismatch', 'resolve');
        }

        try {
            $hash = $this->filesystem->hash($path, 'sha256');
        } catch (Throwable) {
            throw new OfficialArtifactException('artifacts.checksum_mismatch', 'resolve');
        }

        $this->checkedPath($artifact);
        if ($hash !== $record['sha256']) {
            throw new OfficialArtifactException('artifacts.checksum_mismatch', 'resolve');
        }

        return $path;
    }

    public function located(#[SensitiveParameter] string $location): string
    {
        $location = Str::chopStart($location, 'file://');
        if (! Str::startsWith($location, $this->root . '/')) {
            throw new OfficialArtifactException('artifacts.unknown_or_unsafe_path', 'resolve');
        }

        return $this->path(Str::after($location, $this->root . '/'));
    }

    private function checkedPath(#[SensitiveParameter] string $artifact): string
    {
        $path = $this->root;
        foreach (Str::of($artifact)->explode('/') as $segment) {
            $path .= '/' . $segment;
            clearstatcache(true, $path);
            if (is_link($path)) {
                throw new OfficialArtifactException('artifacts.symbolic_link', 'resolve');
            }
        }

        $resolved = realpath($path);
        if ($resolved === false || ! Str::startsWith($resolved, $this->root . '/')) {
            throw new OfficialArtifactException('artifacts.missing_or_unreadable', 'resolve');
        }

        try {
            $isFile     = $this->filesystem->isFile($resolved);
            $isReadable = $isFile && $this->filesystem->isReadable($resolved);
        } catch (Throwable) {
            throw new OfficialArtifactException('artifacts.missing_or_unreadable', 'resolve');
        }

        if (! $isFile || ! $isReadable) {
            throw new OfficialArtifactException('artifacts.missing_or_unreadable', 'resolve');
        }

        return $resolved;
    }
}
