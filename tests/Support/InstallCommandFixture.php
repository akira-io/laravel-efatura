<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

use const PHP_EOL;

final readonly class InstallCommandFixture
{
    private string $originalBasePath;

    private string $basePath;

    public function __construct(public Filesystem $files)
    {
        $this->originalBasePath = app()->basePath();
        $this->basePath         = sys_get_temp_dir() . '/efatura-install-' . Str::uuid()->toString();

        try {
            $this->files->ensureDirectoryExists($this->basePath);
            $this->files->ensureDirectoryExists($this->basePath . '/config');

            app()->setBasePath($this->basePath);
        } catch (Throwable $throwable) {
            try {
                $this->files->deleteDirectory($this->basePath);
            } catch (Throwable) {
                throw $throwable;
            } finally {
                app()->setBasePath($this->originalBasePath);
            }

            throw $throwable;
        }
    }

    public function tearDown(): void
    {
        $this->files->deleteDirectory($this->basePath);
        app()->setBasePath($this->originalBasePath);
    }

    public function envDefaults(): array
    {
        return [
            'EFATURA_TRANSMITTER_TAX_ID'  => '123456789',
            'EFATURA_EMITTER_LED'         => '123',
            'EFATURA_TRANSMITTER_KEY'     => 'secret',
            'EFATURA_MIDDLEWARE_BASE_URL' => 'https://localhost:3443',
            'EFATURA_ENVIRONMENT'         => 'test',
        ];
    }

    public function envContent(array $variables): string
    {
        $lines = [];

        foreach ($variables as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        return Arr::join($lines, PHP_EOL) . PHP_EOL;
    }
}
