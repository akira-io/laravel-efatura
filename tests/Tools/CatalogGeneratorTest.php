<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\CatalogSourceFixture;
use Akira\Efatura\Tools\Catalogs\CatalogGenerator;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->sources = new CatalogSourceFixture;
});

afterEach(function (): void {
    $this->sources->delete();
});

it('writes units keyed by code with cleaned worksheet values', function (): void {
    $this->sources->generator()->write();

    expect($this->sources->catalog('units'))->toBe([
        '05'  => ['status' => 'X', 'code' => '05', 'name' => 'lift', 'description' => '', 'level_category' => '1S', 'symbol' => '', 'conversion_factor' => ''],
        'KGM' => ['status' => '', 'code' => 'KGM', 'name' => 'kilogram', 'description' => 'mass', 'level_category' => 1.5, 'symbol' => 'kg', 'conversion_factor' => 2],
    ]);
});

it('writes every location row with English keys', function (): void {
    $this->sources->generator()->write();

    expect($this->sources->catalog('locations'))->toHaveKeys(['CV', 'CV1', 'PT'])
        ->and($this->sources->catalog('locations')['CV1'])->toBe([
            'code'         => 'CV1',
            'level'        => 2,
            'country'      => 'CV',
            'island'       => 1,
            'municipality' => '',
            'parish'       => '',
            'zone'         => '',
            'place'        => '',
            'name'         => 'SANTO ANTÃO',
        ]);
});

it('writes schema countries with the name published for each country location', function (): void {
    $this->sources->generator()->write();

    expect($this->sources->catalog('countries'))->toBe([
        'CV' => ['code' => 'CV', 'name' => 'CABO VERDE', 'published_name' => 'CAPE VERDE'],
        'ES' => ['code' => 'ES', 'name' => 'SPAIN'],
        'PT' => ['code' => 'PT', 'name' => 'PORTUGAL', 'published_name' => 'PORTUGAL'],
    ]);
});

it('writes schema enumerations with trimmed names and only uppercase currency codes', function (): void {
    $this->sources->generator()->write();

    expect($this->sources->catalog('currencies'))->toBe(['CVE' => ['code' => 'CVE', 'name' => 'Escudo']])
        ->and($this->sources->catalog('payment_means'))->toBe([1 => ['code' => '1', 'name' => 'Instrument not defined']]);
});

it('writes tax exemption reasons from the workbook rows that carry a code', function (): void {
    $this->sources->generator()->write();

    expect($this->sources->catalog('tax_exemption_reasons'))->toBe([
        1 => ['code' => '1', 'description' => 'Bens em segunda mão', 'mention' => 'Isento'],
        2 => ['code' => '2', 'description' => 'Bens da Lista Anexa', 'mention' => 'Isento Art.º 9.º'],
    ]);
});

it('writes minified unescaped JSON objects', function (): void {
    $this->sources->generator()->write();

    expect($this->sources->contents('payment_means'))->toBe('{"1":{"code":"1","name":"Instrument not defined"}}' . "\n")
        ->and($this->sources->contents('locations'))->toContain('"SANTO ANTÃO"')->not->toContain("\n ");
});

it('reports no stale catalogs right after writing them', function (): void {
    $generator = $this->sources->generator();
    $generator->write();

    expect($generator->stale())->toBe([]);
});

it('reports outdated and missing catalogs without rewriting them', function (): void {
    $files     = new Filesystem;
    $generator = $this->sources->generator();
    $generator->write();

    $files->put($this->sources->path('units'), 'stale');
    $files->delete($this->sources->path('locations'));

    expect($generator->stale())->toBe(['units', 'locations'])
        ->and($this->sources->contents('units'))->toBe('stale')
        ->and($this->sources->path('locations'))->not->toBeFile();
});

it('rejects duplicate codes', function (): void {
    $this->sources->units([['', 'KGM', 'kilogram', '', '', '', ''], ['', 'KGM', 'kilogram again', '', '', '', '']]);

    expect(fn (): array => $this->sources->generator()->render())
        ->toThrow(UnexpectedValueException::class, 'Duplicate codes in units');
});

it('rejects tax exemption codes that disagree with the schema', function (): void {
    $this->sources->xsd('CV_EFatura_TaxExemptionReason_v1.0.xsd', ['1' => null, '3' => null]);

    expect(fn (): array => $this->sources->generator()->render())
        ->toThrow(UnexpectedValueException::class, 'Tax exemption codes differ between workbook and XSD sources');
});

it('rejects unknown location columns', function (): void {
    $this->sources->places([['CODIGO', 'NIVEL', 'REGIAO'], ['CV', 1, 'Sotavento']]);

    expect(fn (): array => $this->sources->generator()->render())
        ->toThrow(UnexpectedValueException::class, 'Unknown location column: REGIAO');
});

it('rejects worksheet values that are not text or numbers', function (): void {
    $this->sources->units([['', 'KGM', 'kilogram', '', '', '', true]]);

    expect(fn (): array => $this->sources->generator()->render())
        ->toThrow(UnexpectedValueException::class, 'Unexpected worksheet value: bool');
});

it('rejects unreadable schemas', function (): void {
    new Filesystem()->put($this->sources->root . '/' . CatalogSourceFixture::XSD . 'UNECE_PaymentMeansCode_D19B.xsd', '<broken');

    expect(fn (): array => @$this->sources->generator()->render())
        ->toThrow(UnexpectedValueException::class, 'Unreadable XSD');
});

it('matches the committed catalogs with the bundled official sources', function (): void {
    expect(CatalogGenerator::for(dirname(__DIR__, 2) . '/resources')->stale())->toBe([]);
});
