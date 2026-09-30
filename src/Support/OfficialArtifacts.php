<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Exceptions\OfficialArtifactException;
use SensitiveParameter;

final readonly class OfficialArtifacts
{
    private string $root;

    /** @var array<array-key, mixed> */
    private array $files;

    /** @var array<array-key, mixed> */
    private array $profiles;

    public function __construct(#[SensitiveParameter] string $resources = __DIR__ . '/../../resources')
    {
        $root = realpath($resources);
        if ($root === false || ! is_dir($root)) {
            throw new OfficialArtifactException('artifacts.missing_root', 'load_manifest');
        }

        $this->root   = $root;
        $manifestPath = $this->checkedPath('official-artifacts.json');
        $contents     = @file_get_contents($manifestPath);
        $manifest     = $contents === false ? null : json_decode($contents, true);

        if (! \is_array($manifest) || ! \is_array($manifest['files'] ?? null) || ! \is_array($manifest['signature_profiles'] ?? null)) {
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
        if (preg_match('~[\\\:\x00-\x1f]|(?:^|/)(?:\.{1,2})?(?:/|$)~', $artifact) === 1 || ! \array_key_exists($artifact, $this->files)) {
            throw new OfficialArtifactException('artifacts.unknown_or_unsafe_path', 'resolve');
        }

        $record = $this->files[$artifact];
        if (! \is_array($record) || ! \is_string($record['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $record['sha256']) !== 1) {
            throw new OfficialArtifactException('artifacts.invalid_checksum', 'resolve');
        }

        $path = $this->checkedPath($artifact);
        if (@hash_file('sha256', $path) !== $record['sha256']) {
            throw new OfficialArtifactException('artifacts.checksum_mismatch', 'resolve');
        }

        return $path;
    }

    private function checkedPath(#[SensitiveParameter] string $artifact): string
    {
        $path = $this->root;
        foreach (explode('/', $artifact) as $segment) {
            $path .= '/' . $segment;
            clearstatcache(true, $path);
            if (is_link($path)) {
                throw new OfficialArtifactException('artifacts.symbolic_link', 'resolve');
            }
        }

        $resolved = realpath($path);
        if ($resolved === false || ! str_starts_with($resolved, $this->root . '/') || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new OfficialArtifactException('artifacts.missing_or_unreadable', 'resolve');
        }

        return $resolved;
    }
}
