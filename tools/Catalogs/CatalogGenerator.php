<?php

declare(strict_types=1);

namespace Akira\Efatura\Tools\Catalogs;

use Akira\Efatura\Enums\Catalog;
use Illuminate\Filesystem\Filesystem;
use UnexpectedValueException;

use const JSON_FORCE_OBJECT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class CatalogGenerator
{
    private const string XSD = 'xsd/efatura/2024-05-27/common/';

    private const string UNITS = 'catalogs/source/codigos-de-unidades-de-medidas.xls';

    private const string PLACES = 'catalogs/source/codigo-paises-lugares-cv.xlsx';

    private const string TAX_EXEMPTIONS = 'catalogs/source/Lista-de-Motivos-de-Nao-Liquidacao-de-Imposto.xlsx';

    private const array UNIT_FIELDS = ['status', 'code', 'name', 'description', 'level_category', 'symbol', 'conversion_factor'];

    private const array PLACE_FIELDS = [
        'CODIGO'    => 'code',
        'NIVEL'     => 'level',
        'PAIS'      => 'country',
        'ILHA'      => 'island',
        'CONCELHO'  => 'municipality',
        'FREGUESIA' => 'parish',
        'ZONA'      => 'zone',
        'LUGAR'     => 'place',
        'NOME'      => 'name',
    ];

    public function __construct(
        private Filesystem $files,
        private Workbook $workbook,
        private XsdEnumerations $xsd,
        private string $resources,
    ) {}

    public static function for(string $resources): self
    {
        $files = new Filesystem;

        return new self($files, new Workbook, new XsdEnumerations($files), $resources);
    }

    /**
     * @return array<string, string>
     */
    public function render(): array
    {
        $places = $this->places();

        return collect(Catalog::cases())
            ->mapWithKeys(fn (Catalog $catalog): array => [$catalog->value => $this->encode($catalog, $this->records($catalog, $places))])
            ->all();
    }

    public function write(): void
    {
        foreach ($this->render() as $catalog => $contents) {
            $this->files->put($this->target($catalog), $contents);
        }
    }

    /**
     * @return array<int, string>
     */
    public function stale(): array
    {
        return collect($this->render())
            ->reject(fn (string $contents, string $catalog): bool => $this->isCurrent($catalog, $contents))
            ->keys()
            ->all();
    }

    /**
     * @param  array<int, array<string, float|int|string>> $places
     * @return array<int, array<string, float|int|string>>
     */
    private function records(Catalog $catalog, array $places): array
    {
        return match ($catalog) {
            Catalog::Units               => $this->units(),
            Catalog::Countries           => $this->countries($places),
            Catalog::Locations           => $places,
            Catalog::Currencies          => $this->xsd->records($this->source(self::XSD . 'ISO_ISO3AlphaCurrencyCode_2012-08-31.xsd')),
            Catalog::PaymentMeans        => $this->xsd->records($this->source(self::XSD . 'UNECE_PaymentMeansCode_D19B.xsd')),
            Catalog::TaxExemptionReasons => $this->taxExemptionReasons(),
        };
    }

    /**
     * @param array<int, array<string, float|int|string>> $records
     */
    private function encode(Catalog $catalog, array $records): string
    {
        $indexed = collect($records)->keyBy(fn (array $record): string => (string) $record['code']);

        throw_if($indexed->count() !== \count($records), UnexpectedValueException::class, 'Duplicate codes in ' . $catalog->value);

        return json_encode($indexed->all(), JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @return array<int, array<string, float|int|string>>
     */
    private function units(): array
    {
        return collect($this->workbook->rows($this->source(self::UNITS), 'Unidades Completas'))
            ->skip(1)
            ->map(fn (array $row): array => array_combine(self::UNIT_FIELDS, $row))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, float|int|string>>
     */
    private function places(): array
    {
        $rows   = $this->workbook->rows($this->source(self::PLACES), 'CODIGOS');
        $fields = collect($rows[0] ?? [])
            ->map($this->placeField(...))
            ->all();

        return collect($rows)
            ->skip(1)
            ->map(fn (array $row): array => array_combine($fields, $row))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, float|int|string>> $places
     * @return array<int, array<string, string>>
     */
    private function countries(array $places): array
    {
        $published = collect($places)
            ->where('level', 1)
            ->mapWithKeys(fn (array $place): array => [(string) $place['code'] => (string) $place['name']])
            ->all();

        return collect($this->xsd->records($this->source(self::XSD . 'ISO_ISOTwo-letterCountryCode_SecondEdition2006.xsd')))
            ->map(fn (array $country): array => isset($published[$country['code']])
                ? [...$country, 'published_name' => $published[$country['code']]]
                : $country)
            ->all();
    }

    /**
     * @return array<int, array<string, float|int|string>>
     */
    private function taxExemptionReasons(): array
    {
        $reasons = collect($this->workbook->rows($this->source(self::TAX_EXEMPTIONS), 'Motivos'))
            ->skip(1)
            ->reject(fn (array $row): bool => $row[0] === '')
            ->map(fn (array $row): array => ['code' => (string) $row[0], 'description' => $row[1], 'mention' => $row[2]])
            ->values()
            ->all();

        throw_if(
            array_column($reasons, 'code') !== array_column($this->xsd->records($this->source(self::XSD . 'CV_EFatura_TaxExemptionReason_v1.0.xsd')), 'code'),
            UnexpectedValueException::class,
            'Tax exemption codes differ between workbook and XSD sources',
        );

        return $reasons;
    }

    private function isCurrent(string $catalog, string $contents): bool
    {
        $target = $this->target($catalog);

        return $this->files->isFile($target) && $this->files->get($target) === $contents;
    }

    private function placeField(float|int|string $header): string
    {
        return self::PLACE_FIELDS[(string) $header] ?? throw new UnexpectedValueException('Unknown location column: ' . $header);
    }

    private function source(string $path): string
    {
        return $this->resources . '/' . $path;
    }

    private function target(string $catalog): string
    {
        return $this->source('catalogs/' . $catalog . '.json');
    }
}
