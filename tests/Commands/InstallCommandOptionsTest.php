<?php

declare(strict_types=1);

use Akira\Efatura\EfaturaServiceProvider;
use Akira\Efatura\Tests\Support\InstallCommandFixture;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

use function Pest\Laravel\artisan;

function packageResources(string $directory): array
{
    return collect(new Filesystem()->allFiles(dirname(__DIR__, 2) . '/resources/' . $directory))
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->sort()
        ->values()
        ->all();
}

function publishedResources(string $directory): array
{
    return collect(new Filesystem()->allFiles($directory))
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->sort()
        ->values()
        ->all();
}

beforeEach(function (): void {
    $this->fixture = new InstallCommandFixture(new Filesystem);
    $this->files   = $this->fixture->files;
});

afterEach(function (): void {
    $this->fixture->tearDown();
});

it('installs without a question and leaves the environment file alone without the env option', function (array $options): void {
    $this->files->put(base_path('.env'), "APP_ENV=testing\n");

    artisan('efatura:install', $options)
        ->expectsOutputToContain('Config file published.')
        ->expectsOutputToContain('Sequence migration published.')
        ->expectsOutputToContain('.env left unchanged. Pass --write-env to add the missing variables.')
        ->doesntExpectOutputToContain('Add EFATURA_TRANSMITTER_TAX_ID to .env?')
        ->assertExitCode(0);

    expect($this->files->get(base_path('.env')))->toBe("APP_ENV=testing\n")
        ->and(config_path('efatura.php'))->toBeFile();
})->with([
    'without interaction' => [['--no-interaction' => true]],
    'with another option' => [['--views' => true]],
    'with the force option' => [['--force' => true]],
]);

it('appends every missing variable without a question with the write-env option', function (array $options): void {
    $this->files->put(base_path('.env'), "APP_ENV=testing\nEFATURA_ENVIRONMENT=production");
    $this->files->put(config_path('efatura.php'), '');

    artisan('efatura:install', ['--write-env' => true, ...$options])
        ->expectsOutputToContain('Added EFATURA_TRANSMITTER_TAX_ID to .env.')
        ->expectsOutputToContain('Added EFATURA_MIDDLEWARE_BASE_URL to .env.')
        ->doesntExpectOutputToContain('Add EFATURA_TRANSMITTER_KEY to .env?')
        ->doesntExpectOutputToContain('Added EFATURA_ENVIRONMENT to .env.')
        ->assertExitCode(0);
    $first = $this->files->get(base_path('.env'));
    artisan('efatura:install', ['--write-env' => true, ...$options])->doesntExpectOutputToContain('Added ')->assertExitCode(0);

    expect($first)->toBe(implode(PHP_EOL, [
        'APP_ENV=testing',
        'EFATURA_ENVIRONMENT=production',
        '# akira/efatura',
        'EFATURA_TRANSMITTER_TAX_ID=null',
        'EFATURA_EMITTER_LED=null',
        'EFATURA_TRANSMITTER_KEY=null',
        'EFATURA_MIDDLEWARE_BASE_URL=https://localhost:3443/v1',
    ]) . PHP_EOL)
        ->and($this->files->get(base_path('.env')))->toBe($first);
})->with([
    'interactively'       => [[]],
    'without interaction' => [['--no-interaction' => true]],
]);

it('fails with the write-env option when there is no environment file', function (): void {
    $this->files->put(config_path('efatura.php'), '');

    artisan('efatura:install', ['--write-env' => true, '--no-interaction' => true])
        ->expectsOutputToContain('.env file not found. Create it before running with --write-env.')
        ->assertExitCode(1);

    expect(base_path('.env'))->not->toBeFile();
});

it('publishes the views and translations where their publish tags put them', function (string $option, string $tag, Closure $target, string $source): void {
    $paths = ServiceProvider::pathsToPublish(EfaturaServiceProvider::class, $tag);

    artisan('efatura:install', [$option => true, '--no-interaction' => true])
        ->expectsOutputToContain('Published ' . Str::after($target(), base_path() . '/'))
        ->assertExitCode(0);

    expect(array_map(realpath(...), array_keys($paths)))->toBe([realpath(dirname(__DIR__, 2) . '/resources/' . $source)])
        ->and(array_first($paths))->toEndWith(Str::after($target(), base_path()))
        ->and(publishedResources($target()))->toBe(packageResources($source));
})->with([
    'views'        => ['--views', 'efatura-views', fn (): string => resource_path('views/vendor/efatura'), 'views'],
    'translations' => ['--lang', 'efatura-translations', fn (): string => lang_path('vendor/efatura'), 'lang'],
]);

it('keeps every published file that already exists unless forced', function (string $option, Closure $target): void {
    artisan('efatura:install', [$option => true, '--no-interaction' => true])->assertExitCode(0);
    $customized = collect(new Filesystem()->allFiles($target()))->first();
    $this->files->put($customized->getPathname(), 'customized');
    $relative = Str::after($customized->getPathname(), base_path() . '/');

    artisan('efatura:install', [$option => true, '--no-interaction' => true])
        ->expectsOutputToContain('Kept ' . $relative . '. Pass --force to overwrite it.')
        ->doesntExpectOutputToContain('Published ')
        ->assertExitCode(0);
    $kept = $this->files->get($customized->getPathname());

    artisan('efatura:install', [$option => true, '--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Overwrote ' . $relative . '.')
        ->assertExitCode(0);

    expect($kept)->toBe('customized')
        ->and($this->files->get($customized->getPathname()))->not->toBe('customized');
})->with([
    'views'        => ['--views', fn (): string => resource_path('views/vendor/efatura')],
    'translations' => ['--lang', fn (): string => lang_path('vendor/efatura')],
]);

it('overwrites the config file only when forced', function (): void {
    $this->files->put(config_path('efatura.php'), 'customized');

    artisan('efatura:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Config file already exists. Skipped publishing.')
        ->assertExitCode(0);
    $kept = $this->files->get(config_path('efatura.php'));

    artisan('efatura:install', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Config file overwritten.')
        ->assertExitCode(0);

    expect($kept)->toBe('customized')
        ->and($this->files->get(config_path('efatura.php')))->toBe($this->files->get(dirname(__DIR__, 2) . '/config/efatura.php'));
});

it('never publishes a second sequence migration, even when forced', function (): void {
    $this->files->put(config_path('efatura.php'), '');
    artisan('efatura:install', ['--no-interaction' => true])->assertExitCode(0);

    artisan('efatura:install', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Sequence migration already exists. Skipped publishing.')
        ->assertExitCode(0);

    expect($this->files->glob(database_path('migrations/*_create_efatura_sequences_table.php')))->toHaveCount(1);
});

it('publishes nothing but the config and migration without the views and lang options', function (): void {
    artisan('efatura:install', ['--no-interaction' => true])->assertExitCode(0);

    expect(resource_path('views/vendor/efatura'))->not->toBeDirectory()
        ->and(lang_path('vendor/efatura'))->not->toBeDirectory();
});
