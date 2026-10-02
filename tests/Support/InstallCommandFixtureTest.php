<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\InstallCommandFixture;
use Illuminate\Filesystem\Filesystem;

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

    try {
        $failure = null;

        try {
            new InstallCommandFixture($files);
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }

        expect($failure?->getMessage())->toBe('setup failed')
            ->and($files->createdPath)->not->toBeNull()
            ->and($files->exists($files->createdPath))->toBeFalse()
            ->and(app()->basePath())->toBe($originalBasePath);
    } finally {
        if ($files->createdPath !== null) {
            $files->deleteDirectory($files->createdPath);
        }

        app()->setBasePath($originalBasePath);
    }
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

    try {
        try {
            new InstallCommandFixture($files);
            test()->fail('A failed fixture setup was accepted.');
        } catch (RuntimeException $exception) {
            expect($exception)->toBe($files->setupFailure)
                ->and($files->createdPath)->not->toBeNull()
                ->and(app()->basePath())->toBe($originalBasePath);
        }
    } finally {
        if ($files->createdPath !== null) {
            (new Filesystem)->deleteDirectory($files->createdPath);
        }

        app()->setBasePath($originalBasePath);
    }
});
