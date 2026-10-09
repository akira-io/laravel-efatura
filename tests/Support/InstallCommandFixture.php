<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\EfaturaManager;
use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Dotenv\Repository\RepositoryInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Env;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use ReflectionProperty;
use Throwable;

use const PHP_EOL;

final readonly class InstallCommandFixture
{
    private string $originalBasePath;

    private string $basePath;

    private ?RepositoryInterface $originalEnvironment;

    public function __construct(public Filesystem $files)
    {
        $this->originalBasePath    = app()->basePath();
        $this->originalEnvironment = self::environmentProperty()->getValue();
        $this->basePath            = sys_get_temp_dir() . '/efatura-install-' . Str::uuid()->toString();

        try {
            $this->files->ensureDirectoryExists($this->basePath);
            $this->files->ensureDirectoryExists($this->basePath . '/config');

            app()->setBasePath($this->basePath);
        } catch (Throwable $throwable) {
            try {
                $this->files->deleteDirectory($this->basePath);
            } catch (Throwable) {
                throw $throwable;
            } finally {
                app()->setBasePath($this->originalBasePath);
            }

            throw $throwable;
        }
    }

    public function tearDown(): void
    {
        $this->files->deleteDirectory($this->basePath);
        app()->setBasePath($this->originalBasePath);
        self::environmentProperty()->setValue(null, $this->originalEnvironment);
    }

    public function confirmEveryVariable(PendingCommand $command): PendingCommand
    {
        return $command
            ->expectsConfirmation('Add EFATURA_TRANSMITTER_TAX_ID to .env?', 'yes')
            ->expectsConfirmation('Add EFATURA_EMITTER_LED to .env?', 'yes')
            ->expectsConfirmation('Add EFATURA_TRANSMITTER_KEY to .env?', 'yes')
            ->expectsConfirmation('Add EFATURA_MIDDLEWARE_BASE_URL to .env?', 'yes')
            ->expectsConfirmation('Add EFATURA_ENVIRONMENT to .env?', 'yes');
    }

    public function loadInstalledEnvironment(): void
    {
        $environment = RepositoryBuilder::createWithNoAdapters()->addAdapter(ArrayAdapter::class)->make();
        self::environmentProperty()->setValue(null, $environment);

        Dotenv::create($environment, base_path())->load();
        config()->set('efatura', require config_path('efatura.php'));
        app()->forgetInstance(EfaturaConfig::class);
        app()->forgetInstance(EfaturaManager::class);
    }

    /**
     * @return array<string, string>
     */
    public function envDefaults(): array
    {
        return [
            'EFATURA_TRANSMITTER_TAX_ID'  => '123456789',
            'EFATURA_EMITTER_LED'         => '123',
            'EFATURA_TRANSMITTER_KEY'     => 'secret',
            'EFATURA_MIDDLEWARE_BASE_URL' => 'https://localhost:3443',
            'EFATURA_ENVIRONMENT'         => 'test',
        ];
    }

    /**
     * @param array<string, string> $variables
     */
    public function envContent(array $variables): string
    {
        $lines = [];

        foreach ($variables as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        return Arr::join($lines, PHP_EOL) . PHP_EOL;
    }

    private static function environmentProperty(): ReflectionProperty
    {
        return new ReflectionProperty(Env::class, 'repository');
    }
}
