<?php

declare(strict_types=1);

namespace Akira\Efatura;

use Akira\Efatura\Commands\InstallCommand;
use Akira\Efatura\Contracts\DocumentTypePolicy;
use Akira\Efatura\Support\DefaultDocumentTypePolicy;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EfaturaServiceProvider extends PackageServiceProvider
{
    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(DocumentTypePolicy::class, DefaultDocumentTypePolicy::class);
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('efatura')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasCommand(InstallCommand::class);
    }
}
