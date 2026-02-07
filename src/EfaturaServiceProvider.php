<?php

namespace Akira\Efatura;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Akira\Efatura\Commands\EfaturaCommand;

class EfaturaServiceProvider extends PackageServiceProvider
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
