<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $GLOBALS['efaturaFiles']            = new Filesystem;
    $GLOBALS['efaturaOriginalBasePath'] = app()->basePath();
    $GLOBALS['efaturaBasePath']         = sys_get_temp_dir() . '/efatura-install-' . Str::uuid()->toString();

    testFiles()->ensureDirectoryExists(testBasePath());
    testFiles()->ensureDirectoryExists(testBasePath() . '/config');

    app()->setBasePath(testBasePath());
});

afterEach(function (): void {
    testFiles()->deleteDirectory(testBasePath());
    app()->setBasePath(testOriginalBasePath());
});

function testFiles(): Filesystem
{
    return $GLOBALS['efaturaFiles'];
}

function testBasePath(): string
{
    return $GLOBALS['efaturaBasePath'];
}

function testOriginalBasePath(): string
{
    return $GLOBALS['efaturaOriginalBasePath'];
}

function efaturaEnvDefaults(): array
{
    return [
        'EFATURA_TRANSMITTER_NIF'     => '123456789',
        'EFATURA_LED_CODE'            => 'LED123',
        'EFATURA_TRANSMITTER_KEY'     => 'secret',
        'EFATURA_MIDDLEWARE_BASE_URL' => 'https://localhost:3443',
        'EFATURA_ENVIRONMENT'         => 'test',
    ];
}

function envContent(array $variables): string
{
    $lines = [];

    foreach ($variables as $key => $value) {
        $lines[] = $key . '=' . $value;
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

it('runs without interaction when all variables exist', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, envContent(efaturaEnvDefaults()));
    testFiles()->put($configPath, '');
    testFiles()->ensureDirectoryExists(base_path('vendor/akira/laravel-pdf-invoice'));
    testFiles()->ensureDirectoryExists(base_path('vendor/akira/laravel-qrcode'));

    artisan('efatura:install')->assertExitCode(0);

    artisan('efatura:install')
        ->expectsOutputToContain(trans('efatura.install.config_exists'))
        ->expectsOutputToContain(trans('efatura.install.completed'))
        ->doesntExpectOutputToContain(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_NIF']))
        ->doesntExpectOutputToContain(trans('efatura.install.optional_packages_notice', ['packages' => 'akira/laravel-pdf-invoice, akira/laravel-qrcode']))
        ->assertExitCode(0);

    expect(testFiles()->get($envPath))->toBe(envContent(efaturaEnvDefaults()));
});

it('publishes config when missing', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, envContent(efaturaEnvDefaults()));

    artisan('efatura:install')
        ->expectsOutputToContain(trans('efatura.install.config_published'))
        ->assertExitCode(0);

    expect(testFiles()->exists($configPath))->toBeTrue();
});

it('appends missing env variables when confirmed', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, "APP_ENV=testing\n");
    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_NIF']), 'yes')
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_LED_CODE']), 'yes')
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_KEY']), 'yes')
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_MIDDLEWARE_BASE_URL']), 'yes')
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_ENVIRONMENT']), 'yes')
        ->assertExitCode(0);

    $contents = testFiles()->get($envPath);

    expect($contents)->toContain('# akira/efatura')
        ->and($contents)->toContain('EFATURA_TRANSMITTER_NIF=')
        ->and($contents)->toContain('EFATURA_LED_CODE=')
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
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_KEY']), 'no')
        ->expectsOutputToContain(trans('efatura.install.env_skipped', ['key' => 'EFATURA_TRANSMITTER_KEY']))
        ->doesntExpectOutputToContain(trans('efatura.install.env_added', ['key' => 'EFATURA_TRANSMITTER_KEY']))
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
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_NIF']), 'yes')
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_LED_CODE']), 'yes')
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_KEY']), 'yes')
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_MIDDLEWARE_BASE_URL']), 'yes')
        ->expectsConfirmation(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_ENVIRONMENT']), 'yes')
        ->assertExitCode(0);

    $first = testFiles()->get($envPath);

    artisan('efatura:install')
        ->doesntExpectOutputToContain(trans('efatura.install.env_add_confirm', ['key' => 'EFATURA_TRANSMITTER_NIF']))
        ->assertExitCode(0);

    $second = testFiles()->get($envPath);

    expect($second)->toBe($first);
});

it('handles missing env file', function (): void {
    $configPath = config_path('efatura.php');

    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsOutputToContain(trans('efatura.install.env_missing'))
        ->assertExitCode(0);
});

it('notifies when optional packages are missing', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, envContent(efaturaEnvDefaults()));
    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsOutputToContain(trans('efatura.install.optional_packages_notice', ['packages' => 'akira/laravel-pdf-invoice, akira/laravel-qrcode']))
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
        ->doesntExpectOutputToContain(trans('efatura.install.optional_packages_notice', ['packages' => 'akira/laravel-pdf-invoice, akira/laravel-qrcode']))
        ->assertExitCode(0);
});
