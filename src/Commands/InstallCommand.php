<?php

declare(strict_types=1);

namespace Akira\Efatura\Commands;

use Akira\Efatura\Dfa\DfaLibraries;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

use const PHP_EOL;

#[Signature('efatura:install
    {--write-env : Append the missing EFATURA_* variables to .env without asking}
    {--views : Publish the DFA views}
    {--lang : Publish the translations}
    {--force : Overwrite the config, views and translations that already exist}')]
#[Description('Install akira/efatura configuration')]
final class InstallCommand extends Command
{
    public const string SEQUENCE_MIGRATION = 'create_efatura_sequences_table';

    private const array OPTIONS = ['write-env', 'views', 'lang', 'force'];

    public function handle(Filesystem $filesystem, DfaLibraries $libraries): int
    {
        $this->publishConfig($filesystem);
        $this->publishSequenceMigration($filesystem);

        if ((bool) $this->option('views')) {
            $this->publishDirectory($filesystem, \dirname(__DIR__, 2) . '/resources/views', resource_path('views/vendor/efatura'));
        }

        if ((bool) $this->option('lang')) {
            $this->publishDirectory($filesystem, \dirname(__DIR__, 2) . '/resources/lang', lang_path('vendor/efatura'));
        }

        if (! $this->updateEnvironment($filesystem)) {
            return self::FAILURE;
        }

        $this->notifyOptionalPackages($libraries);

        note(__('efatura::efatura.console.install.completed'));

        return self::SUCCESS;
    }

    private function publishConfig(Filesystem $filesystem): void
    {
        $configPath = config_path('efatura.php');

        $exists = $filesystem->exists($configPath);

        if ($exists && ! (bool) $this->option('force')) {
            note(__('efatura::efatura.console.install.config_exists'));

            return;
        }

        $sourcePath = \dirname(__DIR__, 2) . '/config/efatura.php';

        $filesystem->ensureDirectoryExists(\dirname($configPath));
        $filesystem->copy($sourcePath, $configPath);

        info(__($exists ? 'efatura::efatura.console.install.config_overwritten' : 'efatura::efatura.console.install.config_published'));
    }

    private function publishSequenceMigration(Filesystem $filesystem): void
    {
        $directory = database_path('migrations');

        if ($filesystem->glob($directory . '/*_' . self::SEQUENCE_MIGRATION . '.php') !== []) {
            note(__('efatura::efatura.console.install.migration_exists'));

            return;
        }

        $filesystem->ensureDirectoryExists($directory);
        $filesystem->copy(
            \dirname(__DIR__, 2) . '/database/migrations/' . self::SEQUENCE_MIGRATION . '.php.stub',
            $directory . '/' . now()->format('Y_m_d_His') . '_' . self::SEQUENCE_MIGRATION . '.php',
        );

        info(__('efatura::efatura.console.install.migration_published'));
    }

    private function publishDirectory(Filesystem $filesystem, string $source, string $target): void
    {
        foreach ($filesystem->allFiles($source) as $file) {
            $destination = $target . '/' . $file->getRelativePathname();
            $relative    = Str::after($destination, base_path() . '/');
            $exists      = $filesystem->exists($destination);

            if ($exists && ! (bool) $this->option('force')) {
                note(__('efatura::efatura.console.install.kept', ['path' => $relative]));

                continue;
            }

            $filesystem->ensureDirectoryExists(\dirname($destination));
            $filesystem->copy($file->getPathname(), $destination);

            info(__($exists ? 'efatura::efatura.console.install.overwritten' : 'efatura::efatura.console.install.published', ['path' => $relative]));
        }
    }

    private function updateEnvironment(Filesystem $filesystem): bool
    {
        $append  = (bool) $this->option('write-env');
        $options = collect(self::OPTIONS)->contains(fn (string $option): bool => (bool) $this->option($option));
        $prompts = ! $options && $this->input->isInteractive();

        if (! $append && ! $prompts) {
            note(__('efatura::efatura.console.install.env_untouched'));

            return true;
        }

        if (! $filesystem->exists(base_path('.env'))) {
            $append ? error(__('efatura::efatura.console.install.env_required')) : warning(__('efatura::efatura.console.install.env_missing'));

            return ! $append;
        }

        $this->appendEnvironmentVariables($filesystem, $prompts);

        return true;
    }

    private function appendEnvironmentVariables(Filesystem $filesystem, bool $prompts): void
    {
        $envPath = base_path('.env');

        $contents = $filesystem->get($envPath);

        $variables = [
            'EFATURA_TRANSMITTER_TAX_ID'  => 'null',
            'EFATURA_EMITTER_LED'         => 'null',
            'EFATURA_TRANSMITTER_KEY'     => 'null',
            'EFATURA_MIDDLEWARE_BASE_URL' => 'https://localhost:3443/v1',
            'EFATURA_ENVIRONMENT'         => 'test',
        ];

        $appendLines     = [];
        $shouldAddHeader = ! Str::contains($contents, '# akira/efatura');

        foreach ($variables as $key => $value) {
            if ($this->environmentVariableExists($contents, $key)) {
                continue;
            }

            if ($prompts && ! confirm(__('efatura::efatura.console.install.env_add_confirm', ['key' => $key]))) {
                note(__('efatura::efatura.console.install.env_skipped', ['key' => $key]));

                continue;
            }

            if ($shouldAddHeader && $appendLines === []) {
                $appendLines[] = '# akira/efatura';
            }

            $appendLines[] = $key . '=' . $value;

            info(__('efatura::efatura.console.install.env_added', ['key' => $key]));
        }

        if ($appendLines !== []) {
            $prefix = Str::endsWith($contents, PHP_EOL) ? '' : PHP_EOL;
            $filesystem->append($envPath, $prefix . Arr::join($appendLines, PHP_EOL) . PHP_EOL);
        }
    }

    private function notifyOptionalPackages(DfaLibraries $libraries): void
    {
        $missing = $libraries->missing();

        if ($missing === []) {
            return;
        }

        info(__('efatura::efatura.console.install.optional_packages_notice', ['packages' => implode(', ', $missing)]));
    }

    private function environmentVariableExists(string $contents, string $key): bool
    {
        return Str::isMatch('/^' . preg_quote($key, '/') . '=/m', $contents);
    }
}
