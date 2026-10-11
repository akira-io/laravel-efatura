<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Contracts\ArtifactStore;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;

final class StorageFixtures
{
    public static function iud(int $repository = 3, string $date = '261002', string $emitter = '100200300'): string
    {
        return IdentifierFixtures::withCheckDigit('CV' . $repository . $date . $emitter . '00001010000000071234567890');
    }

    public static function store(?string $disk = 'archive', string $path = 'efatura'): ArtifactStore
    {
        config()->set('efatura.storage.disk', $disk);
        config()->set('efatura.storage.path', $path);

        return resolve(ArtifactStore::class);
    }

    public static function failingDisk(?bool $present, bool $throws): void
    {
        $adapter = new FailingFilesystemAdapter($present);

        Storage::set('archive', new FilesystemAdapter(new Filesystem($adapter), $adapter, ['throw' => $throws]));
    }
}
