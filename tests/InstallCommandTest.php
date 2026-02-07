<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $GLOBALS['efaturaFiles']            = new Filesystem();
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

    artisan('efatura:install')
        ->expectsOutputToContain('Config file already exists. Skipped publishing.')
        ->expectsOutputToContain('akira/efatura installation complete.')
        ->doesntExpectOutputToContain('Add EFATURA')
        ->doesntExpectOutputToContain('Optional PDF/QR packages not detected')
        ->assertExitCode(0);

    expect(testFiles()->get($envPath))->toBe(envContent(efaturaEnvDefaults()));
});

it('publishes config when missing', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, envContent(efaturaEnvDefaults()));

    artisan('efatura:install')
        ->expectsOutputToContain('Config file published.')
        ->assertExitCode(0);

    expect(testFiles()->exists($configPath))->toBeTrue();
});

it('appends missing env variables when confirmed', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, "APP_ENV=testing\n");
    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsConfirmation('Add EFATURA_TRANSMITTER_NIF to .env?', 'yes')
        ->expectsConfirmation('Add EFATURA_LED_CODE to .env?', 'yes')
        ->expectsConfirmation('Add EFATURA_TRANSMITTER_KEY to .env?', 'yes')
        ->expectsConfirmation('Add EFATURA_MIDDLEWARE_BASE_URL to .env?', 'yes')
        ->expectsConfirmation('Add EFATURA_ENVIRONMENT to .env?', 'yes')
        ->expectsOutputToContain('Added EFATURA_TRANSMITTER_NIF to .env.')
        ->expectsOutputToContain('Added EFATURA_LED_CODE to .env.')
        ->expectsOutputToContain('Added EFATURA_TRANSMITTER_KEY to .env.')
        ->expectsOutputToContain('Added EFATURA_MIDDLEWARE_BASE_URL to .env.')
        ->expectsOutputToContain('Added EFATURA_ENVIRONMENT to .env.')
        ->assertExitCode(0);

    $contents = testFiles()->get($envPath);

    expect($contents)->toContain('# akira/efatura');
    expect($contents)->toContain('EFATURA_TRANSMITTER_NIF=');
    expect($contents)->toContain('EFATURA_LED_CODE=');
    expect($contents)->toContain('EFATURA_TRANSMITTER_KEY=');
    expect($contents)->toContain('EFATURA_MIDDLEWARE_BASE_URL=https://localhost:3443');
    expect($contents)->toContain('EFATURA_ENVIRONMENT=test');
});

it('skips env variable insertion when declined', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    $defaults = efaturaEnvDefaults();
    unset($defaults['EFATURA_TRANSMITTER_KEY']);

    testFiles()->put($envPath, envContent($defaults));
    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsConfirmation('Add EFATURA_TRANSMITTER_KEY to .env?', 'no')
        ->expectsOutputToContain('Skipped EFATURA_TRANSMITTER_KEY.')
        ->doesntExpectOutputToContain('Added EFATURA_TRANSMITTER_KEY to .env.')
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
        ->expectsConfirmation('Add EFATURA_TRANSMITTER_NIF to .env?', 'yes')
        ->expectsConfirmation('Add EFATURA_LED_CODE to .env?', 'yes')
        ->expectsConfirmation('Add EFATURA_TRANSMITTER_KEY to .env?', 'yes')
        ->expectsConfirmation('Add EFATURA_MIDDLEWARE_BASE_URL to .env?', 'yes')
        ->expectsConfirmation('Add EFATURA_ENVIRONMENT to .env?', 'yes')
        ->assertExitCode(0);

    $first = testFiles()->get($envPath);

    artisan('efatura:install')
        ->doesntExpectOutputToContain('Add EFATURA')
        ->assertExitCode(0);

    $second = testFiles()->get($envPath);

    expect($second)->toBe($first);
});

it('handles missing env file', function (): void {
    $configPath = config_path('efatura.php');

    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsOutputToContain('.env file not found. Skipped environment updates.')
        ->assertExitCode(0);
});

it('notifies when optional packages are missing', function (): void {
    $envPath    = base_path('.env');
    $configPath = config_path('efatura.php');

    testFiles()->put($envPath, envContent(efaturaEnvDefaults()));
    testFiles()->put($configPath, '');

    artisan('efatura:install')
        ->expectsOutputToContain('Optional PDF/QR packages not detected')
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
        ->doesntExpectOutputToContain('Optional PDF/QR packages not detected')
        ->assertExitCode(0);
});
