<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\EfaturaServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Arr;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Facade;
use Orchestra\Testbench\Foundation\Application as TestbenchApplication;

it('loads partial cached package configuration with nested and host defaults', function (): void {
    $files     = new Filesystem;
    $directory = sys_get_temp_dir() . '/efatura-cached-config-' . bin2hex(random_bytes(12));
    $files->ensureDirectoryExists($directory);
    $cachePath = $directory . '/config.php';
    $files->put($cachePath, '<?php return ' . var_export([
        'filesystems' => ['default' => 'cached-disk'],
        'cache'       => ['default' => 'cached-store'],
        'database'    => ['default' => 'cached-database'],
        'queue'       => ['default' => 'cached-queue', 'connections' => ['cached-queue' => ['queue' => 'cached-jobs']]],
        'efatura'     => ['http' => ['timeout_seconds' => 47], 'storage' => []],
    ], true) . ';');

    $environment              = Env::getRepository();
    $originalApp              = app();
    $hadEnv                   = Arr::exists($_ENV, 'APP_CONFIG_CACHE');
    $previousEnv              = Arr::get($_ENV, 'APP_CONFIG_CACHE');
    $hadServer                = Arr::exists($_SERVER, 'APP_CONFIG_CACHE');
    $previousServer           = Arr::get($_SERVER, 'APP_CONFIG_CACHE');
    $previousProcess          = getenv('APP_CONFIG_CACHE');
    $previousResolved         = $environment->get('APP_CONFIG_CACHE');
    $_ENV['APP_CONFIG_CACHE'] = $_SERVER['APP_CONFIG_CACHE'] = $cachePath;
    putenv('APP_CONFIG_CACHE=' . $cachePath);

    try {
        $isolated = TestbenchApplication::create(
            resolvingCallback: static function ($app): void {
                $app->bind(LoadConfiguration::class, LoadConfiguration::class);
            },
            options: ['extra' => ['providers' => [EfaturaServiceProvider::class]]],
        );

        expect($isolated->configurationIsCached())->toBeTrue();
        $config = $isolated->make(EfaturaConfig::class);

        expect($config->storage->path)->toBe('efatura')
            ->and($config->storage->disk)->toBe('cached-disk')
            ->and($config->certificates->disk)->toBe('cached-disk')
            ->and($config->cache->store)->toBe('cached-store')
            ->and($config->database->connection)->toBe('cached-database')
            ->and($config->queue->connection)->toBe('cached-queue')
            ->and($config->queue->queue)->toBe('cached-jobs')
            ->and($config->http->defaults->timeoutSeconds)->toBe(47)
            ->and($config->http->platform->timeoutSeconds)->toBe(47)
            ->and($config->http->platform->baseUrl)->toBe('https://services.efatura.cv/v1');
    } finally {
        if ($hadEnv) {
            $_ENV['APP_CONFIG_CACHE'] = $previousEnv;
        } else {
            Arr::forget($_ENV, 'APP_CONFIG_CACHE');
        }

        if ($hadServer) {
            $_SERVER['APP_CONFIG_CACHE'] = $previousServer;
        } else {
            Arr::forget($_SERVER, 'APP_CONFIG_CACHE');
        }

        putenv($previousProcess === false ? 'APP_CONFIG_CACHE' : 'APP_CONFIG_CACHE=' . $previousProcess);
        Container::setInstance($originalApp);
        Facade::setFacadeApplication($originalApp);
        $files->deleteDirectory($directory);
    }

    expect(Arr::exists($_ENV, 'APP_CONFIG_CACHE'))->toBe($hadEnv)
        ->and(Arr::exists($_SERVER, 'APP_CONFIG_CACHE'))->toBe($hadServer)
        ->and(getenv('APP_CONFIG_CACHE'))->toBe($previousProcess)
        ->and($environment->get('APP_CONFIG_CACHE'))->toBe($previousResolved);
});
