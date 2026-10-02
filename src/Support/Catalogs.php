<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Exceptions\CatalogException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

use const JSON_THROW_ON_ERROR;

final class Catalogs
{
    /** @var array<string, array{count: int, sources: list<array{path: string, sha256: string}>, records: array<array-key, array<array-key, mixed>>}> */
    private array $loaded = [];

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $directory = __DIR__ . '/../../resources/catalogs',
    ) {}

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return collect(Catalog::cases())
            ->mapWithKeys(fn (Catalog $catalog): array => [$catalog->value => $this->load($catalog)['count']])
            ->all();
    }

    /**
     * @return list<array{path: string, sha256: string}>
     */
    public function sources(Catalog $catalog): array
    {
        return $this->load($catalog)['sources'];
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function records(Catalog $catalog): array
    {
        return array_values($this->load($catalog)['records']);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function find(Catalog $catalog, string $code): ?array
    {
        $record = $this->load($catalog)['records'][$code] ?? null;

        return $record !== null && $this->accepts($catalog, $code, $record) ? $record : null;
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function accepts(Catalog $catalog, string $code, array $record): bool
    {
        return $catalog !== Catalog::Locations || ($record['nivel'] > 1 && Str::startsWith($code, Fiscal::COUNTRY));
    }

    /**
     * @return array{count: int, sources: list<array{path: string, sha256: string}>, records: array<array-key, array<array-key, mixed>>}
     */
    private function load(Catalog $catalog): array
    {
        return $this->loaded[$catalog->value] ??= $this->read($catalog);
    }

    /**
     * @return array{count: int, sources: list<array{path: string, sha256: string}>, records: array<array-key, array<array-key, mixed>>}
     */
    private function read(Catalog $catalog): array
    {
        try {
            $document = $this->filesystem->json($this->directory . '/' . $catalog->value . '.json', JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            throw new CatalogException($throwable instanceof JsonException ? 'catalogs.invalid' : 'catalogs.missing_or_unreadable', 'load', $throwable);
        }

        throw_unless(
            ($document['schema_version'] ?? null) === 1 && \is_int($document['count'] ?? null)
                && \is_array($document['records'] ?? null) && \is_array($document['sources'] ?? null),
            CatalogException::class,
            'catalogs.invalid',
            'load',
        );

        $records = collect($document['records'])
            ->map(fn (mixed $record): array => $this->record($record, $catalog->codeField()))
            ->keyBy($catalog->codeField());

        throw_unless(
            $records->count() === $document['count'] && \count($document['records']) === $document['count'],
            CatalogException::class,
            'catalogs.invalid',
            'load',
        );

        return [
            'count'   => $document['count'],
            'records' => $records->all(),
            'sources' => array_map($this->source(...), array_values($document['sources'])),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function record(mixed $record, string $codeField): array
    {
        throw_unless(\is_array($record) && \is_string($record[$codeField] ?? null), CatalogException::class, 'catalogs.invalid', 'load');

        return $record;
    }

    /**
     * @return array{path: string, sha256: string}
     */
    private function source(mixed $source): array
    {
        $path = \is_array($source) ? ($source['path'] ?? null) : null;
        $hash = \is_array($source) ? ($source['sha256'] ?? null) : null;

        throw_unless(\is_string($path) && \is_string($hash), CatalogException::class, 'catalogs.invalid', 'load');

        return ['path' => $path, 'sha256' => $hash];
    }
}
