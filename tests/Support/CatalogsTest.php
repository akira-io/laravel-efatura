<?php

declare(strict_types=1);

use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Exceptions\CatalogException;
use Akira\Efatura\Support\Catalogs;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->catalogDirectory = sys_get_temp_dir() . '/efatura-catalogs-' . bin2hex(random_bytes(6));
    new Filesystem()->ensureDirectoryExists($this->catalogDirectory);
});

afterEach(function (): void {
    new Filesystem()->deleteDirectory($this->catalogDirectory);
});

it('contains every published row', function (): void {
    $catalogs = resolve(Catalogs::class);

    expect(collect(Catalog::cases())->mapWithKeys(fn (Catalog $catalog): array => [$catalog->value => count($catalogs->records($catalog))])->all())->toBe([
        'units'                 => 2133,
        'countries'             => 249,
        'locations'             => 4211,
        'currencies'            => 179,
        'payment_means'         => 83,
        'tax_exemption_reasons' => 21,
    ]);
});

it('accepts exactly the codes it accepted before the catalogs were regenerated', function (Catalog $catalog, int $count, string $digest): void {
    $catalogs = resolve(Catalogs::class);
    $accepted = collect($catalogs->records($catalog))
        ->pluck('code')
        ->filter(fn (string $code): bool => $catalogs->find($catalog, $code) !== null)
        ->sort(SORT_STRING)
        ->values();

    expect($accepted)->toHaveCount($count)
        ->and(hash('sha256', $accepted->implode("\n")))->toBe($digest);
})->with([
    'units'                 => [Catalog::Units, 2133, 'cacabcb5770bc5bb556d34e916c99ba79d973dd6682c6382ee01678285d672ec'],
    'countries'             => [Catalog::Countries, 249, '2cc33b8f9d0da01bfb0652bae26a661370db79ff7fb4d05435bb935ce108bc2a'],
    'locations'             => [Catalog::Locations, 3971, 'ee038a26f739984b30af8e153db901a01910bc6faab85c3d4eaa48c85adc3c6e'],
    'currencies'            => [Catalog::Currencies, 179, '3a20caac011d8c0e858248b1dfb95384d2fbb3c2a1b8c95ca83491bcc3b09a26'],
    'payment means'         => [Catalog::PaymentMeans, 83, '9dba83721af49167d8a4afba27bb4a6c578129b068cf865fd69c51ef1245c2e9'],
    'tax exemption reasons' => [Catalog::TaxExemptionReasons, 21, 'e5840e53161d8cc12089ce8e9196bc3dbed8b12262ad9e61ba4f158f7c0c4fbe'],
]);

it('finds official codes by catalog with case-sensitive lookups', function (Catalog $catalog, string $code, string $field, mixed $expected): void {
    expect(resolve(Catalogs::class)->find($catalog, $code)[$field])->toBe($expected);
})->with([
    'numeric unit'         => [Catalog::Units, '05', 'code', '05'],
    'named unit'           => [Catalog::Units, 'KGM', 'name', 'kilogram'],
    'country'              => [Catalog::Countries, 'CV', 'code', 'CV'],
    'island'               => [Catalog::Locations, 'CV1', 'name', 'SANTO ANTÃO'],
    'deep location'        => [Catalog::Locations, 'CV111111111011110101', 'level', 6],
    'payment mean'         => [Catalog::PaymentMeans, '1', 'code', '1'],
    'tax exemption reason' => [Catalog::TaxExemptionReasons, '21', 'code', '21'],
    'schema currency IdR'  => [Catalog::Currencies, 'IdR', 'code', 'IdR'],
    'fiscal currency'      => [Catalog::Currencies, 'CVE', 'code', 'CVE'],
    'precious metal'       => [Catalog::Currencies, 'XAU', 'code', 'XAU'],
    'no currency code'     => [Catalog::Currencies, 'XXX', 'code', 'XXX'],
]);

it('returns null for unknown, miscased or non fiscal location codes', function (Catalog $catalog, string $code): void {
    expect(resolve(Catalogs::class)->find($catalog, $code))->toBeNull();
})->with([
    [Catalog::Units, 'kgm'],
    [Catalog::Countries, 'AN'],
    [Catalog::Countries, 'XK'],
    [Catalog::Countries, 'cv'],
    [Catalog::Locations, 'CV'],
    [Catalog::Locations, 'AN'],
    [Catalog::Locations, 'XK'],
    [Catalog::Locations, 'cv1'],
    [Catalog::PaymentMeans, '01'],
    [Catalog::TaxExemptionReasons, '022'],
    [Catalog::Currencies, 'IDR'],
    [Catalog::Currencies, 'idr'],
    [Catalog::Currencies, 'ZZZ'],
]);

it('keeps all country rows of the location catalog for audit', function (): void {
    $rows = collect(resolve(Catalogs::class)->records(Catalog::Locations));

    expect($rows->count())->toBe(4211)
        ->and($rows->where('level', 1)->count())->toBe(240)
        ->and($rows->where('level', '>', 1)->count())->toBe(3971);
});

it('does not let callers mutate cached catalog values', function (): void {
    $catalogs     = resolve(Catalogs::class);
    $unit         = $catalogs->find(Catalog::Units, 'KGM');
    $unit['name'] = 'changed';

    expect($catalogs->find(Catalog::Units, 'KGM')['name'])->toBe('kilogram');
});

it('is a container singleton', function (): void {
    expect(resolve(Catalogs::class))->toBe(resolve(Catalogs::class));
});

it('reads each catalog file once', function (): void {
    $filesystem = new Filesystem;
    $path       = $this->catalogDirectory . '/units.json';
    $filesystem->copy(dirname(__DIR__, 2) . '/resources/catalogs/units.json', $path);
    $catalogs = new Catalogs($filesystem, $this->catalogDirectory);

    $first = $catalogs->find(Catalog::Units, 'KGM');
    $filesystem->delete($path);

    expect($catalogs->find(Catalog::Units, 'MTR')['code'])->toBe('MTR')
        ->and($first['code'])->toBe('KGM');
});

it('rejects missing catalog resources', function (): void {
    expect(fn (): ?array => new Catalogs(new Filesystem, $this->catalogDirectory)->find(Catalog::Units, 'KGM'))
        ->toThrow(CatalogException::class, 'catalogs.missing_or_unreadable');
});

it('rejects malformed catalog resources', function (string $contents): void {
    new Filesystem()->put($this->catalogDirectory . '/units.json', $contents);

    expect(fn (): ?array => new Catalogs(new Filesystem, $this->catalogDirectory)->find(Catalog::Units, 'KGM'))
        ->toThrow(CatalogException::class, 'catalogs.invalid');
})->with([
    'invalid json'        => '{invalid',
    'scalar document'     => '1',
    'null record'         => '{"KGM":null}',
    'record without code' => '{"KGM":{"name":"kilogram"}}',
    'code mismatch'       => '{"KGM":{"code":"MTR"}}',
    'list of records'     => '[{"code":"KGM"}]',
]);
