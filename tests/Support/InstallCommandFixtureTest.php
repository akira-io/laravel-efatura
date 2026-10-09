<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\InstallCommandFixture;
use Illuminate\Filesystem\Filesystem;

afterEach(function (): void {
    new Filesystem()->deleteDirectory((string) $this->files->createdPath);
});

it('cleans a partial fixture setup while preserving the original failure', function (): void {
    $originalBasePath = app()->basePath();
    $files            = new class extends Filesystem
    {
        public ?string $createdPath = null;

        private int $calls = 0;

        public function ensureDirectoryExists($path, $mode = 0o755, $recursive = true): void
        {
            parent::ensureDirectoryExists($path, $mode, $recursive);

            if (++$this->calls === 1) {
                $this->createdPath = $path;

                return;
            }

            throw new RuntimeException('setup failed');
        }
    };
    $this->files = $files;

    expect(fn (): InstallCommandFixture => new InstallCommandFixture($files))
        ->toThrow(function (RuntimeException $exception) use ($files, $originalBasePath): void {
            expect($exception->getMessage())->toBe('setup failed')
                ->and($files->createdPath)->toStartWith(sys_get_temp_dir() . '/efatura-install-')
                ->and($files->exists($files->createdPath))->toBeFalse()
                ->and(app()->basePath())->toBe($originalBasePath);
        });
});

it('preserves setup failure and base path when fixture cleanup also fails', function (): void {
    $originalBasePath = app()->basePath();
    $files            = new class extends Filesystem
    {
        public ?string $createdPath = null;

        public RuntimeException $setupFailure;

        private int $calls = 0;

        public function __construct()
        {
            $this->setupFailure = new RuntimeException('setup failed');
        }

        public function ensureDirectoryExists($path, $mode = 0o755, $recursive = true): void
        {
            parent::ensureDirectoryExists($path, $mode, $recursive);

            if (++$this->calls === 1) {
                $this->createdPath = $path;

                return;
            }

            throw $this->setupFailure;
        }

        public function deleteDirectory($directory, $preserve = false): void
        {
            throw new RuntimeException('cleanup failed');
        }
    };
    $this->files = $files;

    expect(fn (): InstallCommandFixture => new InstallCommandFixture($files))
        ->toThrow(function (RuntimeException $exception) use ($files, $originalBasePath): void {
            expect($exception)->toBe($files->setupFailure)
                ->and($files->createdPath)->toStartWith(sys_get_temp_dir() . '/efatura-install-')
                ->and(app()->basePath())->toBe($originalBasePath);
        });
});
