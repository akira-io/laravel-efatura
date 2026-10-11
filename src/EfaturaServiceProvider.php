<?php

declare(strict_types=1);

namespace Akira\Efatura;

use Akira\Efatura\Actions\BuildIudAction;
use Akira\Efatura\Actions\ConvertDocumentCurrencyAction;
use Akira\Efatura\Commands\DoctorCommand;
use Akira\Efatura\Commands\InstallCommand;
use Akira\Efatura\Commands\ValidateCommand;
use Akira\Efatura\Configuration\CacheConfig;
use Akira\Efatura\Configuration\DatabaseConfig;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Configuration\LoadRoutesConfig;
use Akira\Efatura\Configuration\RoutesConfig;
use Akira\Efatura\Configuration\StorageConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\ArtifactStore;
use Akira\Efatura\Contracts\CertificateLoader;
use Akira\Efatura\Contracts\DfaRenderer;
use Akira\Efatura\Contracts\ExchangeRateProvider;
use Akira\Efatura\Contracts\Packager;
use Akira\Efatura\Contracts\QrCodeRenderer;
use Akira\Efatura\Contracts\RefreshTokenRepository;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Contracts\XmlSigner;
use Akira\Efatura\Currency\DeferredExchangeRateProvider;
use Akira\Efatura\Currency\ExchangeRateProviderResolver;
use Akira\Efatura\Dfa\BaconQrCodeRenderer;
use Akira\Efatura\Dfa\DompdfDfaRenderer;
use Akira\Efatura\Dfa\DompdfOptions;
use Akira\Efatura\Http\Middleware\EnsureDiagnosticsRoutesEnabled;
use Akira\Efatura\Money\CatalogCurrency;
use Akira\Efatura\OAuth\RefreshTokenAccessTokenProvider;
use Akira\Efatura\Packaging\ZipArchivePackager;
use Akira\Efatura\Sequence\DatabaseSequenceStore;
use Akira\Efatura\Signing\OpenSslCertificateLoader;
use Akira\Efatura\Signing\XadesBesXmlSigner;
use Akira\Efatura\Storage\FilesystemArtifactStore;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Transport\TransportResolver;
use Akira\Efatura\Xml\LibxmlSchemaValidator;
use Carbon\FactoryImmutable;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Contracts\Routing\Registrar;
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
        $this->app->singleton(function (Application $app): RoutesConfig {
            $loader = $app->make(LoadRoutesConfig::class);

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
        $this->app->bind(CertificateLoader::class, OpenSslCertificateLoader::class);
        $this->app->bind(XmlSigner::class, XadesBesXmlSigner::class);
        $this->app->bind(Packager::class, ZipArchivePackager::class);
        $this->app->bind(QrCodeRenderer::class, BaconQrCodeRenderer::class);
        $this->app->bind(DfaRenderer::class, DompdfDfaRenderer::class);
        $this->app->when(DompdfOptions::class)->needs('$defaultRoot')
            ->give(fn (Application $app): string => $app->storagePath('framework/cache/efatura-dompdf'));
        $this->app->when(FilesystemArtifactStore::class)->needs(StorageConfig::class)
            ->give(fn (): StorageConfig => $this->app->make(EfaturaConfig::class)->storage);
        $this->app->when(FilesystemArtifactStore::class)->needs(CacheConfig::class)
            ->give(fn (): CacheConfig => $this->app->make(EfaturaConfig::class)->cache);
        $this->app->bind(ArtifactStore::class, FilesystemArtifactStore::class);
        $this->app->bind(Transport::class, fn (Application $app): Transport => $app->make(TransportResolver::class)->driver());
        $this->app->bind(
            ExchangeRateProvider::class,
            fn (Application $app): ExchangeRateProvider => $app->make(ExchangeRateProviderResolver::class)->provider(),
        );
        $this->app->when(ConvertDocumentCurrencyAction::class)->needs(ExchangeRateProvider::class)->give(DeferredExchangeRateProvider::class);
    }

    #[Override]
    public function packageBooted(): void
    {
        if ($this->app->bound(RefreshTokenRepository::class)) {
            $this->app->bindIf(AccessTokenProvider::class, RefreshTokenAccessTokenProvider::class);
        }

        $this->registerDiagnosticsRoutes($this->app->make(RoutesConfig::class));
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('efatura')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasMigration(InstallCommand::SEQUENCE_MIGRATION)
            ->hasCommands([InstallCommand::class, DoctorCommand::class, ValidateCommand::class]);
    }

    private function registerDiagnosticsRoutes(RoutesConfig $routes): void
    {
        if (! $routes->enabled || ($this->app instanceof CachesRoutes && $this->app->routesAreCached())) {
            return;
        }

        $this->app->make(Registrar::class)->group(
            ['prefix' => $routes->prefix, 'as' => $routes->name, 'middleware' => [EnsureDiagnosticsRoutesEnabled::class, ...$routes->middleware]],
            __DIR__ . '/../routes/efatura.php',
        );
    }
}
