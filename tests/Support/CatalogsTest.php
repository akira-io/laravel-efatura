<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\CatalogException;
use Akira\Efatura\Support\Catalogs;
use Illuminate\Filesystem\Filesystem;

function catalogResourceFilesystem(?string $contents): Filesystem
{
    return new class ($contents) extends Filesystem
    {
        public function __construct(private readonly ?string $contents) {}

        public function get($path, $lock = false): string
        {
            if ($this->contents === null) {
                throw new RuntimeException('Catalog resource is unreadable');
            }

            return $this->contents;
        }
    };
}

it('contains every published row and preserves source checksums', function (): void {
    $catalogs = new Catalogs;
    $official = json_decode(file_get_contents(dirname(__DIR__, 2) . '/resources/official-artifacts.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($catalogs->counts())->toBe([
        'units'                 => 2133,
        'countries'             => 249,
        'locations'             => 4211,
        'currencies'            => 179,
        'payment_means'         => 83,
        'tax_exemption_reasons' => 21,
    ]);

    foreach (['units', 'countries', 'locations', 'currencies', 'payment_means', 'tax_exemption_reasons'] as $name) {
        foreach ($catalogs->sources($name) as $source) {
            expect(hash_file('sha256', dirname(__DIR__, 2) . '/resources/' . $source['path']))->toBe($source['sha256']);
            expect($source['sha256'])->toBe($official['files'][$source['path']]['sha256']);
        }
    }
});

it('preserves source codes, names, and case-sensitive lookups', function (): void {
    $catalogs = new Catalogs;

    expect($catalogs->unit('05')['code'])->toBe('05')
        ->and($catalogs->unit('KGM')['name'])->toBe('kilogram')
        ->and($catalogs->unit('kgm'))->toBeNull()
        ->and($catalogs->country('CV')['code'])->toBe('CV')
        ->and($catalogs->country('AN'))->toBeNull()
        ->and($catalogs->country('XK'))->toBeNull()
        ->and($catalogs->country('cv'))->toBeNull()
        ->and($catalogs->location('CV1')['nome'])->toBe('SANTO ANTÃO')
        ->and($catalogs->location('CV'))->toBeNull()
        ->and($catalogs->location('AN'))->toBeNull()
        ->and($catalogs->location('cv1'))->toBeNull()
        ->and($catalogs->paymentMean('1')['code'])->toBe('1')
        ->and($catalogs->paymentMean('01'))->toBeNull()
        ->and($catalogs->taxExemptionReason('21')['code'])->toBe('21')
        ->and($catalogs->taxExemptionReason('022'))->toBeNull();
});

it('keeps schema currency codes exactly, including the IdR anomaly and special codes', function (): void {
    $catalogs = new Catalogs;

    expect($catalogs->currency('IdR')['code'])->toBe('IdR')
        ->and($catalogs->currency('IDR'))->toBeNull()
        ->and($catalogs->currency('idr'))->toBeNull()
        ->and($catalogs->currency('CVE')['code'])->toBe('CVE')
        ->and($catalogs->currency('XAU')['code'])->toBe('XAU')
        ->and($catalogs->currency('XXX')['code'])->toBe('XXX')
        ->and($catalogs->currency('ZZZ'))->toBeNull();
});

it('keeps all country rows for audit but accepts only CV descendants as locations', function (): void {
    $catalogs = new Catalogs;
    $rows     = collect($catalogs->records('locations'));

    expect($rows->count())->toBe(4211)
        ->and($rows->where('nivel', 1)->count())->toBe(240)
        ->and($rows->where('nivel', '>', 1)->count())->toBe(3971)
        ->and($catalogs->location('CV111111111011110101')['nivel'])->toBe(6)
        ->and($catalogs->location('XK'))->toBeNull();
});

it('does not let callers mutate cached catalog values', function (): void {
    $catalogs     = new Catalogs;
    $unit         = $catalogs->unit('KGM');
    $unit['name'] = 'changed';

    expect($catalogs->unit('KGM')['name'])->toBe('kilogram');
});

it('rejects unknown or malformed catalog resources', function (): void {
    expect(fn (): array => new Catalogs()->records('unknown'))->toThrow(CatalogException::class);

    expect(fn (): array => new Catalogs(catalogResourceFilesystem('{invalid'))->records('units'))->toThrow(CatalogException::class);

    expect(fn (): array => new Catalogs(catalogResourceFilesystem('{"schema_version":1,"records":null}'))->records('units'))->toThrow(CatalogException::class);

    foreach ([
        '{"schema_version":1,"count":1,"records":[null],"sources":[]}',
        '{"schema_version":1,"count":1,"records":[["a"]],"sources":[]}',
        '{"schema_version":1,"count":0,"records":[],"sources":[null]}',
        '{"schema_version":1,"count":1,"records":[],"sources":[]}',
    ] as $invalid) {
        expect(fn (): array => new Catalogs(catalogResourceFilesystem($invalid))->records('units'))->toThrow(CatalogException::class);
    }

    expect(fn (): array => new Catalogs(catalogResourceFilesystem(null))->records('units'))->toThrow(CatalogException::class);
});
