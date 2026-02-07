<?php

declare(strict_types=1);

namespace Akira\Efatura\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function dirname;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

use const PHP_EOL;

final class InstallCommand extends Command
{
    public $signature = 'efatura:install';

    public $description = 'Install akira/efatura configuration';

    public function handle(Filesystem $filesystem): int
    {
        $this->publishConfig($filesystem);
        $this->updateEnvironmentFile($filesystem);
        $this->notifyOptionalPackages($filesystem);

        note('akira/efatura installation complete.');

        return self::SUCCESS;
    }

    private function publishConfig(Filesystem $filesystem): void
    {
        $configPath = config_path('efatura.php');

        if ($filesystem->exists($configPath)) {
            note('Config file already exists. Skipped publishing.');

            return;
        }

        $sourcePath = dirname(__DIR__, 2) . '/config/efatura.php';

        $filesystem->ensureDirectoryExists(dirname($configPath));
        $filesystem->copy($sourcePath, $configPath);

        info('Config file published.');
    }

    private function updateEnvironmentFile(Filesystem $filesystem): void
    {
        $envPath = base_path('.env');

        if (! $filesystem->exists($envPath)) {
            warning('.env file not found. Skipped environment updates.');

            return;
        }

        $contents = $filesystem->get($envPath);

        $variables = [
            'EFATURA_TRANSMITTER_NIF'     => '',
            'EFATURA_LED_CODE'            => '',
            'EFATURA_TRANSMITTER_KEY'     => '',
            'EFATURA_MIDDLEWARE_BASE_URL' => 'https://localhost:3443',
            'EFATURA_ENVIRONMENT'         => 'test',
        ];

        $appendLines     = [];
        $shouldAddHeader = ! str_contains($contents, '# akira/efatura');

        foreach ($variables as $key => $value) {
            if ($this->environmentVariableExists($contents, $key)) {
                continue;
            }

            $confirmed = confirm("Add {$key} to .env?");

            if (! $confirmed) {
                note("Skipped {$key}.");

                continue;
            }

            if ($shouldAddHeader && $appendLines === []) {
                $appendLines[] = '# akira/efatura';
            }

            $appendLines[] = $key . '=' . $value;

            info("Added {$key} to .env.");
        }

        if ($appendLines !== []) {
            $prefix = Str::endsWith($contents, PHP_EOL) ? '' : PHP_EOL;
            $filesystem->append($envPath, $prefix . implode(PHP_EOL, $appendLines) . PHP_EOL);
        }
    }

    private function notifyOptionalPackages(Filesystem $filesystem): void
    {
        $missing = [];

        if (! $filesystem->isDirectory(base_path('vendor/akira/laravel-pdf-invoice'))) {
            $missing[] = 'akira/laravel-pdf-invoice';
        }

        if (! $filesystem->isDirectory(base_path('vendor/akira/laravel-qrcode'))) {
            $missing[] = 'akira/laravel-qrcode';
        }

        if ($missing === []) {
            return;
        }

        $packages = implode(', ', $missing);

        info("Optional PDF/QR packages not detected: {$packages}. PDF and QR generation are optional. The recommended packages work out of the box when installed with akira/efatura.");
    }

    private function environmentVariableExists(string $contents, string $key): bool
    {
        return preg_match('/^' . preg_quote($key, '/') . '=/m', $contents) === 1;
    }
}
