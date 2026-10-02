<?php

declare(strict_types=1);

use Akira\Efatura\Commands\InstallCommand;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\Tests\Support\InstallCommandFixture;
use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Env;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $GLOBALS['efaturaInstallFixture'] = null;
    $GLOBALS['efaturaInstallFixture'] = new InstallCommandFixture(new Filesystem);
});

afterEach(function (): void {
    $GLOBALS['efaturaInstallFixture']?->tearDown();
});

function installFixture(): InstallCommandFixture
{
    return $GLOBALS['efaturaInstallFixture'];
}

function testFiles(): Filesystem
{
    return installFixture()->files;
}

function efaturaEnvDefaults(): array
{
    return installFixture()->envDefaults();
}

function envContent(array $variables): string
{
    return installFixture()->envContent($variables);
}

it('uses the Laravel 13 command signature attribute', function (): void {
    $attributes = new ReflectionClass(InstallCommand::class)
        ->getAttributes(Signature::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->signature)->toBe('efatura:install')
        ->and(resolve(InstallCommand::class)->getDescription())
        ->toBe('Install akira/efatura configuration');
});

it('runs without interaction when all variables exist', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, envContent(efaturaEnvDefaults()));
    testFiles()->put($configPath, '');
    testFiles()->ensureDirectoryExists(base_path('vendor/akira/laravel-pdf-invoice'));
    testFiles()->ensureDirectoryExists(base_path('vendor/akira/laravel-qrcode'));

    artisan('efatura:install')->assertExitCode(0);

    artisan('efatura:install')
        ->expectsOutputToContain(trans('efatura::efatura.install.config_exists'))
        ->expectsOutputToContain(trans('efatura::efatura.install.completed'))
        ->doesntExpectOutputToContain(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_TAX_ID']))
        ->doesntExpectOutputToContain(trans('efatura::efatura.install.optional_packages_notice', ['packages' => 'akira/laravel-pdf-invoice, akira/laravel-qrcode']))
        ->assertExitCode(0);

    expect(testFiles()->get($envPath))->toBe(envContent(efaturaEnvDefaults()));
});

it('publishes config when missing', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, envContent(efaturaEnvDefaults()));

    artisan('efatura:install')
        ->expectsOutputToContain(trans('efatura::efatura.install.config_published'))
        ->assertExitCode(0);

    expect(testFiles()->exists($configPath))->toBeTrue();
});

it('appends missing env variables when confirmed', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, "APP_ENV=testing\n");
    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_TAX_ID']), 'yes')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_EMITTER_LED']), 'yes')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_KEY']), 'yes')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_MIDDLEWARE_BASE_URL']), 'yes')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_ENVIRONMENT']), 'yes')
        ->assertExitCode(0);

    $contents = testFiles()->get($envPath);

    expect($contents)->toContain('# akira/efatura')
        ->and($contents)->toContain('EFATURA_TRANSMITTER_TAX_ID=')
        ->and($contents)->toContain('EFATURA_EMITTER_LED=')
        ->and($contents)->toContain('EFATURA_TRANSMITTER_KEY=')
        ->and($contents)->toContain('EFATURA_MIDDLEWARE_BASE_URL=https://localhost:3443')
        ->and($contents)->toContain('EFATURA_ENVIRONMENT=test');
});

it('skips env variable insertion when declined', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    $defaults = efaturaEnvDefaults();
    unset($defaults['EFATURA_TRANSMITTER_KEY']);

    testFiles()->put($envPath, envContent($defaults));
    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_KEY']), 'no')
        ->expectsOutputToContain(trans('efatura::efatura.install.env_skipped', ['key' => 'EFATURA_TRANSMITTER_KEY']))
        ->doesntExpectOutputToContain(trans('efatura::efatura.install.env_added', ['key' => 'EFATURA_TRANSMITTER_KEY']))
        ->assertExitCode(0);

    $contents = testFiles()->get($envPath);

    expect($contents)->not->toContain('EFATURA_TRANSMITTER_KEY=');
});

it('is idempotent when run twice', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, "APP_ENV=testing\n");
    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_TAX_ID']), 'yes')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_EMITTER_LED']), 'yes')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_KEY']), 'yes')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_MIDDLEWARE_BASE_URL']), 'yes')
        ->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_ENVIRONMENT']), 'yes')
        ->assertExitCode(0);

    $first = testFiles()->get($envPath);

    artisan('efatura:install')
        ->doesntExpectOutputToContain(trans('efatura::efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_TAX_ID']))
        ->assertExitCode(0);

    $second = testFiles()->get($envPath);

    expect($second)->toBe($first);
});

it('handles missing env file', function (): void {
    $configPath = config_path('efatura.php');

    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsOutputToContain(trans('efatura::efatura.install.env_missing'))
        ->assertExitCode(0);
});

it('notifies when optional packages are missing', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, envContent(efaturaEnvDefaults()));
    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsOutputToContain(trans('efatura::efatura.install.optional_packages_notice', ['packages' => 'akira/laravel-pdf-invoice, akira/laravel-qrcode']))
        ->assertExitCode(0);
});

it('does not notify when optional packages are present', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, envContent(efaturaEnvDefaults()));
    testFiles()->put($configPath, '');
    testFiles()->ensureDirectoryExists(base_path('vendor/akira/laravel-pdf-invoice'));
    testFiles()->ensureDirectoryExists(base_path('vendor/akira/laravel-qrcode'));

    artisan('efatura:install')
        ->doesntExpectOutputToContain(trans('efatura::efatura.install.optional_packages_notice', ['packages' => 'akira/laravel-pdf-invoice, akira/laravel-qrcode']))
        ->assertExitCode(0);
});

it('resolves the manager from the installed environment and published config', function (): void {
    testFiles()->put(base_path('.env'), "APP_ENV=testing\n");
    $command = artisan('efatura:install');
    foreach (collect(efaturaEnvDefaults())->keys() as $key) {
        $command->expectsConfirmation(trans('efatura::efatura.install.env_add_confirm', ['key' => $key]), 'yes');
    }

    $command->assertExitCode(0)->run();

    $property    = new ReflectionProperty(Env::class, 'repository');
    $original    = $property->getValue();
    $environment = RepositoryBuilder::createWithNoAdapters()->addAdapter(ArrayAdapter::class)->make();
    $property->setValue(null, $environment);

    try {
        Dotenv::create($environment, base_path())->load();
        config()->set('efatura', require config_path('efatura.php'));
        $config = resolve(EfaturaManager::class)->config();

        expect($config)->toBe(resolve(EfaturaConfig::class))
            ->and($config->emitter)->toBeNull()
            ->and($config->transmitter->taxId)->toBeNull()
            ->and($config->transmitter->middlewareKey)->toBeNull()
            ->and($config->transmitter->oauth->clientSecret)->toBeNull()
            ->and($config->environment->repositoryCode())->toBe(3)
            ->and($config->http->middleware->baseUrl)->toBe('https://localhost:3443');

        $contents = testFiles()->get(base_path('.env'));
        expect($contents)->toContain('EFATURA_TRANSMITTER_TAX_ID=null', 'EFATURA_EMITTER_LED=null');
        testFiles()->put(base_path('.env'), Str::replace(
            ['EFATURA_TRANSMITTER_TAX_ID=null', 'EFATURA_EMITTER_LED=null'],
            ['EFATURA_TRANSMITTER_TAX_ID=123456789', 'EFATURA_EMITTER_LED=123'],
            $contents,
        ));
        Dotenv::create($environment, base_path())->load();
        config()->set('efatura', require config_path('efatura.php'));
        app()->forgetInstance(EfaturaConfig::class);
        app()->forgetInstance(EfaturaManager::class);

        $configured = resolve(EfaturaManager::class)->config();
        expect($configured->transmitter->taxId)->toBe('123456789')
            ->and($configured->emitter->led)->toBe(123);
    } finally {
        $property->setValue(null, $original);
    }
});
