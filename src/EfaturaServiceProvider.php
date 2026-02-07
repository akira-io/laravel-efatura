<?php

declare(strict_types=1);

namespace Akira\Efatura;

use Akira\Efatura\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EfaturaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('efatura')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(InstallCommand::class);
    }
}
