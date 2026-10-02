<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Exceptions\CatalogException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

use const JSON_THROW_ON_ERROR;

final class Catalogs
{
    private const array NAMES = [
        'units', 'countries', 'locations', 'currencies', 'payment_means', 'tax_exemption_reasons',
    ];

    /** @var array<string, array{count: int, sources: list<array{path: string, sha256: string}>, records: list<array<string, mixed>>}> */
    private array $loaded = [];

    public function __construct(private readonly ?Filesystem $filesystem = null) {}

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return collect(self::NAMES)
            ->mapWithKeys(fn (string $name): array => [$name => $this->load($name)['count']])
            ->all();
    }

    /**
     * @return list<array{path: string, sha256: string}>
     */
    public function sources(string $catalog): array
    {
        return $this->load($catalog)['sources'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function records(string $catalog): array
    {
        return $this->load($catalog)['records'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function unit(string $code): ?array
    {
        return $this->find('units', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function country(string $code): ?array
    {
        return $this->find('countries', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function location(string $code): ?array
    {
        $row = $this->find('locations', $code, 'codigo');

        return $row !== null && $row['nivel'] > 1 && Str::startsWith($code, Fiscal::COUNTRY) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currency(string $code): ?array
    {
        return $this->find('currencies', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function paymentMean(string $code): ?array
    {
        return $this->find('payment_means', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function taxExemptionReason(string $code): ?array
    {
        return $this->find('tax_exemption_reasons', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(string $catalog, string $code, string $field = 'code'): ?array
    {
        return Arr::first($this->records($catalog), static fn (array $record): bool => $record[$field] === $code);
    }

    /**
     * @return array{count: int, sources: list<array{path: string, sha256: string}>, records: list<array<string, mixed>>}
     */
    private function load(string $catalog): array
    {
        if (! \in_array($catalog, self::NAMES, true)) {
            throw new CatalogException('catalogs.unknown', 'lookup');
        }

        if (isset($this->loaded[$catalog])) {
            return $this->loaded[$catalog];
        }

        $path = \dirname(__DIR__, 2) . \sprintf('/resources/catalogs/%s.json', $catalog);

        try {
            $json = ($this->filesystem ?? new Filesystem)->get($path);
        } catch (Throwable $throwable) {
            throw new CatalogException('catalogs.missing_or_unreadable', 'load', $throwable);
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new CatalogException('catalogs.invalid', 'load', $jsonException);
        }

        if (! \is_array($data) || ($data['schema_version'] ?? null) !== 1
            || ! \is_int($data['count'] ?? null) || ! \is_array($data['records'] ?? null)
            || ! \is_array($data['sources'] ?? null)) {
            throw new CatalogException('catalogs.invalid', 'load');
        }

        $records = [];
        foreach ($data['records'] as $record) {
            if (! \is_array($record)) {
                throw new CatalogException('catalogs.invalid', 'load');
            }

            $fields = [];
            foreach ($record as $field => $value) {
                if (! \is_string($field)) {
                    throw new CatalogException('catalogs.invalid', 'load');
                }

                $fields[$field] = $value;
            }

            $records[] = $fields;
        }

        $sources = [];
        foreach ($data['sources'] as $source) {
            if (! \is_array($source) || ! \is_string($source['path'] ?? null) || ! \is_string($source['sha256'] ?? null)) {
                throw new CatalogException('catalogs.invalid', 'load');
            }

            $sources[] = ['path' => $source['path'], 'sha256' => $source['sha256']];
        }

        if ($data['count'] !== \count($records)) {
            throw new CatalogException('catalogs.invalid', 'load');
        }

        return $this->loaded[$catalog] = ['count' => $data['count'], 'records' => $records, 'sources' => $sources];
    }
}
