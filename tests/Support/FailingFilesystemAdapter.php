<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

final readonly class FailingFilesystemAdapter implements FilesystemAdapter
{
    public function __construct(private ?bool $present) {}

    public function fileExists(string $path): bool
    {
        return $this->present ?? throw UnableToCheckFileExistence::forLocation($path);
    }

    public function directoryExists(string $path): bool
    {
        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        throw UnableToWriteFile::atLocation($path, 'disk is down');
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        throw UnableToWriteFile::atLocation($path, 'disk is down');
    }

    public function read(string $path): string
    {
        throw UnableToReadFile::fromLocation($path, 'disk is down');
    }

    public function readStream(string $path): void
    {
        throw UnableToReadFile::fromLocation($path, 'disk is down');
    }

    public function delete(string $path): void
    {
        throw UnableToDeleteFile::atLocation($path, 'disk is down');
    }

    public function deleteDirectory(string $path): void
    {
        throw UnableToDeleteDirectory::atLocation($path, 'disk is down');
    }

    public function createDirectory(string $path, Config $config): void
    {
        throw UnableToCreateDirectory::atLocation($path, 'disk is down');
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'disk is down');
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, 'disk is down');
    }

    public function mimeType(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::mimeType($path, 'disk is down');
    }

    public function lastModified(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::lastModified($path, 'disk is down');
    }

    public function fileSize(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::fileSize($path, 'disk is down');
    }

    public function listContents(string $path, bool $deep): iterable
    {
        throw UnableToListContents::atLocation($path, $deep, new UnableToReadFile('disk is down'));
    }

    public function move(string $source, string $destination, Config $config): void
    {
        throw UnableToMoveFile::fromLocationTo($source, $destination);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        throw UnableToCopyFile::fromLocationTo($source, $destination);
    }
}
