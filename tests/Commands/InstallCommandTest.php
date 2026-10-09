<?php

declare(strict_types=1);

use Akira\Efatura\Commands\InstallCommand;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\Tests\Support\InstallCommandFixture;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->fixture = new InstallCommandFixture(new Filesystem);
    $this->files   = $this->fixture->files;
});

afterEach(function (): void {
    $this->fixture->tearDown();
});

it('declares its signature and description with Laravel command attributes', function (): void {
    $command = new ReflectionClass(InstallCommand::class);

    expect($command->getAttributes(Signature::class)[0]->newInstance()->signature)->toBe('efatura:install')
        ->and($command->getAttributes(Description::class)[0]->newInstance()->description)->toBe('Install akira/efatura configuration')
        ->and($command->getConstructor()?->getDeclaringClass()->getName())->not->toBe(InstallCommand::class)
        ->and(resolve(InstallCommand::class)->getDescription())->toBe('Install akira/efatura configuration');
});

it('runs without interaction when all variables exist', function (): void {
    $envPath = base_path('.env');

    $this->files->put($envPath, $this->fixture->envContent($this->fixture->envDefaults()));
    $this->files->put(config_path('efatura.php'), '');
    $this->files->ensureDirectoryExists(base_path('vendor/akira/laravel-pdf-invoice'));
    $this->files->ensureDirectoryExists(base_path('vendor/akira/laravel-qrcode'));

    artisan('efatura:install')
        ->expectsOutputToContain('Config file already exists. Skipped publishing.')
        ->expectsOutputToContain('akira/efatura installation complete.')
        ->doesntExpectOutputToContain('Add EFATURA_TRANSMITTER_TAX_ID to .env?')
        ->doesntExpectOutputToContain('Optional PDF/QR packages not detected')
        ->assertExitCode(0);

    expect($this->files->get($envPath))->toBe($this->fixture->envContent($this->fixture->envDefaults()));
});

it('publishes config when missing', function (): void {
    $this->files->put(base_path('.env'), $this->fixture->envContent($this->fixture->envDefaults()));

    artisan('efatura:install')
        ->expectsOutputToContain('Config file published.')
        ->assertExitCode(0);

    expect($this->files->get(config_path('efatura.php')))->toBe($this->files->get(dirname(__DIR__, 2) . '/config/efatura.php'));
});

it('appends missing env variables when confirmed', function (): void {
    $envPath = base_path('.env');

    $this->files->put($envPath, "APP_ENV=testing\n");
    $this->files->put(config_path('efatura.php'), '');

    $this->fixture->confirmEveryVariable(artisan('efatura:install'))
        ->expectsOutputToContain('Added EFATURA_ENVIRONMENT to .env.')
        ->assertExitCode(0);

    $contents = $this->files->get($envPath);

    expect($contents)->toContain('# akira/efatura')
        ->and($contents)->toContain('EFATURA_TRANSMITTER_TAX_ID=')
        ->and($contents)->toContain('EFATURA_EMITTER_LED=')
        ->and($contents)->toContain('EFATURA_TRANSMITTER_KEY=')
        ->and($contents)->toContain('EFATURA_MIDDLEWARE_BASE_URL=https://localhost:3443')
        ->and($contents)->toContain('EFATURA_ENVIRONMENT=test');
});

it('skips env variable insertion when declined', function (): void {
    $envPath  = base_path('.env');
    $defaults = $this->fixture->envDefaults();
    unset($defaults['EFATURA_TRANSMITTER_KEY']);

    $this->files->put($envPath, $this->fixture->envContent($defaults));
    $this->files->put(config_path('efatura.php'), '');

    artisan('efatura:install')
        ->expectsConfirmation('Add EFATURA_TRANSMITTER_KEY to .env?', 'no')
        ->expectsOutputToContain('Skipped EFATURA_TRANSMITTER_KEY.')
        ->doesntExpectOutputToContain('Added EFATURA_TRANSMITTER_KEY to .env.')
        ->assertExitCode(0);

    expect($this->files->get($envPath))->not->toContain('EFATURA_TRANSMITTER_KEY=');
});

it('is idempotent when run twice', function (): void {
    $envPath = base_path('.env');

    $this->files->put($envPath, "APP_ENV=testing\n");
    $this->files->put(config_path('efatura.php'), '');

    $this->fixture->confirmEveryVariable(artisan('efatura:install'))->assertExitCode(0);

    $first = $this->files->get($envPath);

    artisan('efatura:install')
        ->doesntExpectOutputToContain('Add EFATURA_TRANSMITTER_TAX_ID to .env?')
        ->assertExitCode(0);

    expect($this->files->get($envPath))->toBe($first);
});

it('handles missing env file', function (): void {
    $this->files->put(config_path('efatura.php'), '');

    artisan('efatura:install')
        ->expectsOutputToContain('.env file not found. Skipped environment updates.')
        ->assertExitCode(0);
});

it('notifies when optional packages are missing', function (): void {
    $this->files->put(base_path('.env'), $this->fixture->envContent($this->fixture->envDefaults()));
    $this->files->put(config_path('efatura.php'), '');

    artisan('efatura:install')
        ->expectsOutputToContain('Optional PDF/QR packages not detected: akira/laravel-pdf-invoice, akira/laravel-qrcode. PDF and QR generation are optional.')
        ->assertExitCode(0);
});

it('does not notify when optional packages are present', function (): void {
    $this->files->put(base_path('.env'), $this->fixture->envContent($this->fixture->envDefaults()));
    $this->files->put(config_path('efatura.php'), '');
    $this->files->ensureDirectoryExists(base_path('vendor/akira/laravel-pdf-invoice'));
    $this->files->ensureDirectoryExists(base_path('vendor/akira/laravel-qrcode'));

    artisan('efatura:install')
        ->doesntExpectOutputToContain('Optional PDF/QR packages not detected')
        ->assertExitCode(0);
});

it('resolves the manager without identities from the freshly installed environment', function (): void {
    $this->files->put(base_path('.env'), "APP_ENV=testing\n");
    $this->fixture->confirmEveryVariable(artisan('efatura:install'))->assertExitCode(0)->run();

    $this->fixture->loadInstalledEnvironment();
    $config = resolve(EfaturaManager::class)->config();

    expect($this->files->get(base_path('.env')))->toContain('EFATURA_TRANSMITTER_TAX_ID=null')
        ->and($this->files->get(base_path('.env')))->toContain('EFATURA_EMITTER_LED=null')
        ->and($config)->toBe(resolve(EfaturaConfig::class))
        ->and($config->emitter)->toBeNull()
        ->and($config->transmitter->taxId)->toBeNull()
        ->and($config->transmitter->middlewareKey)->toBeNull()
        ->and($config->transmitter->oauth->clientSecret)->toBeNull()
        ->and($config->environment->repositoryCode())->toBe(3)
        ->and($config->http->middleware->baseUrl)->toBe('https://localhost:3443');
});

it('resolves configured identities once the installed environment is filled in', function (): void {
    $envPath = base_path('.env');
    $this->files->put($envPath, "APP_ENV=testing\n");
    $this->fixture->confirmEveryVariable(artisan('efatura:install'))->assertExitCode(0)->run();
    $this->files->put($envPath, Str::replace(
        ['EFATURA_TRANSMITTER_TAX_ID=null', 'EFATURA_EMITTER_LED=null'],
        ['EFATURA_TRANSMITTER_TAX_ID=123456789', 'EFATURA_EMITTER_LED=123'],
        $this->files->get($envPath),
    ));

    $this->fixture->loadInstalledEnvironment();
    $configured = resolve(EfaturaManager::class)->config();

    expect($configured->transmitter->taxId)->toBe('123456789')
        ->and($configured->emitter->led)->toBe(123);
});
