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

it('contains every published row and preserves source checksums', function (): void {
    $catalogs = resolve(Catalogs::class);
    $official = json_decode(file_get_contents(dirname(__DIR__, 2) . '/resources/official-artifacts.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($catalogs->counts())->toBe([
        'units'                 => 2133,
        'countries'             => 249,
        'locations'             => 4211,
        'currencies'            => 179,
        'payment_means'         => 83,
        'tax_exemption_reasons' => 21,
    ]);

    foreach (Catalog::cases() as $catalog) {
        foreach ($catalogs->sources($catalog) as $source) {
            expect(hash_file('sha256', dirname(__DIR__, 2) . '/resources/' . $source['path']))->toBe($source['sha256'])
                ->and($source['sha256'])->toBe($official['files'][$source['path']]['sha256']);
        }
    }
});

it('finds official codes by catalog with case-sensitive lookups', function (Catalog $catalog, string $code, string $field, mixed $expected): void {
    expect(resolve(Catalogs::class)->find($catalog, $code)[$field])->toBe($expected);
})->with([
    'numeric unit'         => [Catalog::Units, '05', 'code', '05'],
    'named unit'           => [Catalog::Units, 'KGM', 'name', 'kilogram'],
    'country'              => [Catalog::Countries, 'CV', 'code', 'CV'],
    'island'               => [Catalog::Locations, 'CV1', 'nome', 'SANTO ANTÃO'],
    'deep location'        => [Catalog::Locations, 'CV111111111011110101', 'nivel', 6],
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
        ->and($rows->where('nivel', 1)->count())->toBe(240)
        ->and($rows->where('nivel', '>', 1)->count())->toBe(3971);
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
    'missing count'       => '{"schema_version":1,"records":[],"sources":[]}',
    'unknown schema'      => '{"schema_version":2,"count":0,"records":[],"sources":[]}',
    'null records'        => '{"schema_version":1,"count":0,"records":null,"sources":[]}',
    'null record'         => '{"schema_version":1,"count":1,"records":[null],"sources":[]}',
    'record without code' => '{"schema_version":1,"count":1,"records":[["a"]],"sources":[]}',
    'duplicate code'      => '{"schema_version":1,"count":2,"records":[{"code":"A"},{"code":"A"}],"sources":[]}',
    'null source'         => '{"schema_version":1,"count":0,"records":[],"sources":[null]}',
    'count mismatch'      => '{"schema_version":1,"count":1,"records":[],"sources":[]}',
]);
