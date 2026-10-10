<?php

declare(strict_types=1);

namespace Akira\Efatura;

use Akira\Efatura\Actions\BuildIudAction;
use Akira\Efatura\Commands\InstallCommand;
use Akira\Efatura\Configuration\DatabaseConfig;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Money\CatalogCurrency;
use Akira\Efatura\Sequence\DatabaseSequenceStore;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\LibxmlSchemaValidator;
use Carbon\FactoryImmutable;
use Illuminate\Foundation\Application;
use Override;
use Psr\Clock\ClockInterface;
use Random\Engine\Secure;
use Random\Randomizer;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EfaturaServiceProvider extends PackageServiceProvider
{
    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(function (Application $app): EfaturaConfig {
            $loader = $app->make(LoadEfaturaConfig::class);

            return $loader();
        });
        $this->app->singleton(EfaturaManager::class);
        $this->app->singleton(Catalogs::class);
        CatalogCurrency::resolveCatalogsUsing(fn (): Catalogs => $this->app->make(Catalogs::class));
        $this->app->singleton(ClockInterface::class, fn (): ClockInterface => new FactoryImmutable(['timezone' => Fiscal::TIMEZONE]));
        $this->app->when(BuildIudAction::class)->needs(Randomizer::class)->give(fn (): Randomizer => new Randomizer(new Secure));
        $this->app->bind(SchemaValidator::class, LibxmlSchemaValidator::class);
        $this->app->when(DatabaseSequenceStore::class)->needs(DatabaseConfig::class)
            ->give(fn (): DatabaseConfig => $this->app->make(EfaturaConfig::class)->database);
        $this->app->bind(SequenceStore::class, DatabaseSequenceStore::class);
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('efatura')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigration('create_efatura_sequences_table')
            ->hasCommand(InstallCommand::class);
    }
}
