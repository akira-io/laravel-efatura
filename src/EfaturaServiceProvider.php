<?php

declare(strict_types=1);

namespace Akira\Efatura;

use Akira\Efatura\Commands\EfaturaCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EfaturaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('efatura')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_efatura_table')
            ->hasCommand(EfaturaCommand::class);
    }
}
