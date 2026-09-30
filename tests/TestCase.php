<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests;

use Akira\Efatura\EfaturaServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected Filesystem $files;

    protected string $basePath;

    protected string $originalBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Akira\Efatura\Database\Factories\\' . class_basename($modelName) . 'Factory',
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            EfaturaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
