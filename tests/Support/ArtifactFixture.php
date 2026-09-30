<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Illuminate\Filesystem\Filesystem;

use const JSON_THROW_ON_ERROR;

final readonly class ArtifactFixture
{
    public string $root;

    public function __construct()
    {
        $this->root = realpath(sys_get_temp_dir()) . '/efatura-artifacts-' . bin2hex(random_bytes(12));
        mkdir($this->root);
        mkdir($this->root . '/nested');
        file_put_contents($this->root . '/nested/example.xsd', "original\r\nbytes\r\n");
        $this->manifest();
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function manifest(array $changes = []): void
    {
        $manifest = array_replace([
            'signature_profiles' => ['EnvelopedSignature' => 'nested/example.xsd'],
            'files'              => ['nested/example.xsd' => ['sha256' => hash('sha256', "original\r\nbytes\r\n")]],
        ], $changes);
        file_put_contents($this->root . '/official-artifacts.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    public function remove(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
    }
}
