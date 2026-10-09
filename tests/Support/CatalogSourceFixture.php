<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Tools\Catalogs\CatalogGenerator;
use Illuminate\Filesystem\Filesystem;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

use const JSON_THROW_ON_ERROR;

final readonly class CatalogSourceFixture
{
    public const string XSD = 'xsd/efatura/2024-05-27/common/';

    public string $root;

    private Filesystem $files;

    public function __construct()
    {
        $this->files = new Filesystem;
        $this->root  = sys_get_temp_dir() . '/efatura-catalog-sources-' . bin2hex(random_bytes(6));
        $this->files->ensureDirectoryExists($this->root . '/catalogs/source');
        $this->files->ensureDirectoryExists($this->root . '/' . self::XSD);

        $this->units([
            ['X', '05', 'lift', '', '1S', '', ''],
            ['', 'KGM', 'kilogram', 'mass', 1.5, 'kg', 2.0],
        ]);
        $this->places([
            ['CODIGO', 'NIVEL', 'PAIS', 'ILHA', 'CONCELHO', 'FREGUESIA', 'ZONA', 'LUGAR', 'NOME'],
            ['CV', 1, 'CV', '', '', '', '', '', 'CAPE VERDE'],
            ['CV1', 2, 'CV', 1, '', '', '', '', 'SANTO ANTÃO'],
            ['PT', 1, 'PT', '', '', '', '', '', 'PORTUGAL'],
        ]);
        $this->taxExemptions([[1, 'Bens em segunda mão', 'Isento'], [2, 'Bens da Lista Anexa', 'Isento Art.º 9.º'], [null, null, null]]);
        $this->xsd('ISO_ISOTwo-letterCountryCode_SecondEdition2006.xsd', ['CV' => 'CABO VERDE', 'ES' => 'SPAIN', 'PT' => 'PORTUGAL']);
        $this->xsd('ISO_ISO3AlphaCurrencyCode_2012-08-31.xsd', ['CVE' => ' Escudo ', 'IdR' => null]);
        $this->xsd('UNECE_PaymentMeansCode_D19B.xsd', ['1' => 'Instrument not defined']);
        $this->xsd('CV_EFatura_TaxExemptionReason_v1.0.xsd', ['1' => null, '2' => null]);
    }

    /**
     * @param list<list<mixed>> $rows
     */
    public function units(array $rows): void
    {
        $header = ['Status', 'Common Code', 'Name', 'Description', 'Level / Category', 'Symbol', 'Conversion Factor'];

        $this->workbook('codigos-de-unidades-de-medidas.xls', 'Unidades Completas', [$header, ...$rows], 'Xls');
    }

    /**
     * @param list<list<mixed>> $rows
     */
    public function places(array $rows): void
    {
        $this->workbook('codigo-paises-lugares-cv.xlsx', 'CODIGOS', $rows, 'Xlsx');
    }

    /**
     * @param list<list<mixed>> $rows
     */
    public function taxExemptions(array $rows): void
    {
        $this->workbook('Lista-de-Motivos-de-Nao-Liquidacao-de-Imposto.xlsx', 'Motivos', [['Código', 'Descrição', 'Menção'], ...$rows], 'Xlsx');
    }

    /**
     * @param array<array-key, string|null> $enumerations
     */
    public function xsd(string $file, array $enumerations): void
    {
        $entries = collect($enumerations)->map(fn (?string $name, int|string $code): string => \sprintf(
            '<xsd:enumeration value="%s">%s</xsd:enumeration>',
            $code,
            $name === null ? '' : '<xsd:annotation><xsd:documentation><ccts:Name>' . $name . '</ccts:Name></xsd:documentation></xsd:annotation>',
        ))->implode('');

        $this->files->put($this->root . '/' . self::XSD . $file, \sprintf(
            '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:ccts="%s"><xsd:simpleType name="Code"><xsd:restriction base="xsd:token">%s</xsd:restriction></xsd:simpleType></xsd:schema>',
            'urn:un:unece:uncefact:documentation:standard:CoreComponentsTechnicalSpecification:2',
            $entries,
        ));
    }

    public function generator(): CatalogGenerator
    {
        return CatalogGenerator::for($this->root);
    }

    public function path(string $catalog): string
    {
        return $this->root . '/catalogs/' . $catalog . '.json';
    }

    public function contents(string $catalog): string
    {
        return $this->files->get($this->path($catalog));
    }

    /**
     * @return array<array-key, mixed>
     */
    public function catalog(string $catalog): array
    {
        return $this->files->json($this->path($catalog), JSON_THROW_ON_ERROR);
    }

    public function delete(): void
    {
        $this->files->deleteDirectory($this->root);
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function workbook(string $file, string $sheet, array $rows, string $type): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle($sheet)->fromArray($rows, null, 'A1', true);

        IOFactory::createWriter($spreadsheet, $type)->save($this->root . '/catalogs/source/' . $file);
    }
}
