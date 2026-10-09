<?php

declare(strict_types=1);

use Akira\Efatura\Data\PayableAlternativeAmountData;
use Akira\Efatura\Exceptions\DefinitionException;
use Akira\Efatura\Money\CatalogCurrency;
use Akira\Efatura\Money\FiscalMoney;
use Akira\Efatura\Support\Catalogs;
use Brick\Money\Money;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->catalogDirectory = sys_get_temp_dir() . '/efatura-currencies-' . bin2hex(random_bytes(6));
    $filesystem             = new Filesystem;
    $filesystem->ensureDirectoryExists($this->catalogDirectory);
    $filesystem->put($this->catalogDirectory . '/currencies.json', json_encode(['CVE' => ['code' => 'CVE', 'name' => 'Escudo']], JSON_THROW_ON_ERROR));

    $this->app->instance(Catalogs::class, new Catalogs($filesystem, $this->catalogDirectory));
});

afterEach(function (): void {
    new Filesystem()->deleteDirectory($this->catalogDirectory);
});

it('reads currencies from the bound catalogs in fiscal money', function (): void {
    expect((string) FiscalMoney::cve('1')->getAmount())->toBe('1.00')
        ->and(fn (): Money => FiscalMoney::of('1', 'EUR'))
        ->toFailValidationOn('amount', 'Currency must be an uppercase code of the official currency catalog.');
});

it('reads currencies from the bound catalogs in the foreign money cast', function (): void {
    expect(fn (): PayableAlternativeAmountData => PayableAlternativeAmountData::factory()->withoutValidation()
        ->from(['value' => '1', 'currencyCode' => 'EUR', 'exchangeRate' => '1']))
        ->toFailValidationOn('currencyCode', 'Currency must be an uppercase code of the official currency catalog.');
});

it('refuses to resolve currencies without the service provider', function (): void {
    CatalogCurrency::resolveCatalogsUsing(null);

    expect(fn (): bool => CatalogCurrency::isOfficial('CVE'))
        ->toThrow(DefinitionException::class, 'Currency catalogs are resolved through the efatura service provider, which is not registered.');
});
