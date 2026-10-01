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
