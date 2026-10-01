<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Support\OfficialArtifacts;
use Illuminate\Filesystem\Filesystem;

use const JSON_THROW_ON_ERROR;

final readonly class ArtifactFixture
{
    public string $root;

    private Filesystem $files;

    public function __construct()
    {
        $this->files = new Filesystem;
        $this->root  = realpath(sys_get_temp_dir()) . '/efatura-artifacts-' . bin2hex(random_bytes(12));
        $this->files->ensureDirectoryExists($this->root . '/nested');
        $this->files->put($this->root . '/nested/example.xsd', "original\r\nbytes\r\n");
        $this->manifest();
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function manifest(array $changes = []): void
    {
        $manifest = collect([
            'signature_profiles' => ['EnvelopedSignature' => 'nested/example.xsd'],
            'files'              => ['nested/example.xsd' => ['sha256' => hash('sha256', "original\r\nbytes\r\n"), 'size' => 17]],
        ])->replace($changes)->all();
        $this->files->put($this->root . '/official-artifacts.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    public function artifacts(?Filesystem $filesystem = null): OfficialArtifacts
    {
        return new OfficialArtifacts($filesystem ?? $this->files, $this->root);
    }

    public function remove(): void
    {
        $this->files->deleteDirectory($this->root);
    }
}
