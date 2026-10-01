<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Providers;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\EfaturaServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Orchestra\Testbench\TestCase;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

use const JSON_THROW_ON_ERROR;

final class PackageDiscoveryTest extends TestCase
{
    private string $discoveryRoot;

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        (new Filesystem)->deleteDirectory($this->discoveryRoot);
    }

    public static function discoveryModes(): array
    {
        return ['declared provider' => [true], 'absent declaration' => [false]];
    }

    #[DataProvider('discoveryModes')]
    public function test_discovery_depends_on_the_composer_provider_declaration(bool $discovery): void
    {
        self::assertSame($discovery, $this->app->providerIsLoaded(EfaturaServiceProvider::class));
        self::assertSame($discovery, $this->app->bound(EfaturaConfig::class));

        if ($discovery) {
            $manager = $this->app->make(EfaturaManager::class);
            self::assertSame($manager->config(), $this->app->make(EfaturaConfig::class));
            self::assertSame(3, $manager->config()->environment->repositoryCode());
        }
    }

    #[Override]
    protected function resolveApplicationResolvingCallback($app): void
    {
        parent::resolveApplicationResolvingCallback($app);

        $files               = new Filesystem;
        $this->discoveryRoot = sys_get_temp_dir() . '/efatura-discovery-' . bin2hex(random_bytes(12));
        $files->ensureDirectoryExists($this->discoveryRoot . '/vendor/composer');
        $files->ensureDirectoryExists($this->discoveryRoot . '/bootstrap/cache');

        $composer = $files->json(__DIR__ . '/../../composer.json');
        if (! $this->providedData()[0]) {
            unset($composer['extra']['laravel']['providers']);
        }

        $files->put($this->discoveryRoot . '/vendor/composer/installed.json', json_encode(['packages' => [$composer]], JSON_THROW_ON_ERROR));

        $app->useBootstrapPath($this->discoveryRoot . '/bootstrap');
        $manifest             = new PackageManifest($files, $this->discoveryRoot, $app->getCachedPackagesPath());
        $manifest->vendorPath = $this->discoveryRoot . '/vendor';

        $app->instance(PackageManifest::class, $manifest);
    }
}
