<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests;

use Akira\Efatura\EfaturaServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    protected Filesystem $files;

    protected string $basePath;

    protected string $originalBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Akira\Efatura\Database\Factories\\' . class_basename($modelName) . 'Factory',
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            EfaturaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
