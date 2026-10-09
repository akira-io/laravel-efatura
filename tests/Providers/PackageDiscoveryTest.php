<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Providers;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\EfaturaServiceProvider;
use Akira\Efatura\Tests\Support\ComposerMetadata;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Arr;
use Orchestra\Testbench\TestCase;
use Override;

use const JSON_THROW_ON_ERROR;

final class PackageDiscoveryTest extends TestCase
{
    private const array UNDECLARED = ['test_skips_the_provider_without_a_composer_declaration' => 'extra.laravel.providers'];

    private string $discoveryRoot;

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->discoveryRoot);
    }

    public function test_registers_the_provider_declared_in_composer(): void
    {
        $manager = $this->app->make(EfaturaManager::class);

        self::assertTrue($this->app->providerIsLoaded(EfaturaServiceProvider::class));
        self::assertSame($manager->config(), $this->app->make(EfaturaConfig::class));
        self::assertSame(3, $manager->config()->environment->repositoryCode());
    }

    public function test_skips_the_provider_without_a_composer_declaration(): void
    {
        self::assertFalse($this->app->providerIsLoaded(EfaturaServiceProvider::class));
        self::assertFalse($this->app->bound(EfaturaConfig::class));
    }

    #[Override]
    protected function resolveApplicationResolvingCallback($app): void
    {
        parent::resolveApplicationResolvingCallback($app);

        $files               = new Filesystem;
        $this->discoveryRoot = sys_get_temp_dir() . '/efatura-discovery-' . bin2hex(random_bytes(12));
        $files->ensureDirectoryExists($this->discoveryRoot . '/vendor/composer');
        $files->ensureDirectoryExists($this->discoveryRoot . '/bootstrap/cache');

        $composer = ComposerMetadata::read();
        Arr::forget($composer, self::UNDECLARED[$this->name()] ?? []);

        $files->put($this->discoveryRoot . '/vendor/composer/installed.json', json_encode(['packages' => [$composer]], JSON_THROW_ON_ERROR));

        $app->useBootstrapPath($this->discoveryRoot . '/bootstrap');
        $manifest             = new PackageManifest($files, $this->discoveryRoot, $app->getCachedPackagesPath());
        $manifest->vendorPath = $this->discoveryRoot . '/vendor';

        $app->instance(PackageManifest::class, $manifest);
    }
}
