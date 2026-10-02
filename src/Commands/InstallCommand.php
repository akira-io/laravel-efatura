<?php

declare(strict_types=1);

namespace Akira\Efatura\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

use const PHP_EOL;

#[Signature('efatura:install')]
final class InstallCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('efatura::efatura.install.command_description'));
    }

    public function handle(Filesystem $filesystem): int
    {
        $this->publishConfig($filesystem);
        $this->updateEnvironmentFile($filesystem);
        $this->notifyOptionalPackages($filesystem);

        note(__('efatura::efatura.install.completed'));

        return self::SUCCESS;
    }

    private function publishConfig(Filesystem $filesystem): void
    {
        $configPath = config_path('efatura.php');

        if ($filesystem->exists($configPath)) {
            note(__('efatura::efatura.install.config_exists'));

            return;
        }

        $sourcePath = \dirname(__DIR__, 2) . '/config/efatura.php';

        $filesystem->ensureDirectoryExists(\dirname($configPath));
        $filesystem->copy($sourcePath, $configPath);

        info(__('efatura::efatura.install.config_published'));
    }

    private function updateEnvironmentFile(Filesystem $filesystem): void
    {
        $envPath = base_path('.env');

        if (! $filesystem->exists($envPath)) {
            warning(__('efatura::efatura.install.env_missing'));

            return;
        }

        $contents = $filesystem->get($envPath);

        $variables = [
            'EFATURA_TRANSMITTER_TAX_ID'  => 'null',
            'EFATURA_EMITTER_LED'         => 'null',
            'EFATURA_TRANSMITTER_KEY'     => 'null',
            'EFATURA_MIDDLEWARE_BASE_URL' => 'https://localhost:3443',
            'EFATURA_ENVIRONMENT'         => 'test',
        ];

        $appendLines     = [];
        $shouldAddHeader = ! Str::contains($contents, '# akira/efatura');

        foreach ($variables as $key => $value) {
            if ($this->environmentVariableExists($contents, $key)) {
                continue;
            }

            $confirmed = confirm(__('efatura::efatura.install.env_add_confirm', ['key' => $key]));

            if (! $confirmed) {
                note(__('efatura::efatura.install.env_skipped', ['key' => $key]));

                continue;
            }

            if ($shouldAddHeader && $appendLines === []) {
                $appendLines[] = '# akira/efatura';
            }

            $appendLines[] = $key . '=' . $value;

            info(__('efatura::efatura.install.env_added', ['key' => $key]));
        }

        if ($appendLines !== []) {
            $prefix = Str::endsWith($contents, PHP_EOL) ? '' : PHP_EOL;
            $filesystem->append($envPath, $prefix . Arr::join($appendLines, PHP_EOL) . PHP_EOL);
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

        $packages = Arr::join($missing, ', ');

        info(__('efatura::efatura.install.optional_packages_notice', ['packages' => $packages]));
    }

    private function environmentVariableExists(string $contents, string $key): bool
    {
        return Str::isMatch('/^' . preg_quote($key, '/') . '=/m', $contents);
    }
}
