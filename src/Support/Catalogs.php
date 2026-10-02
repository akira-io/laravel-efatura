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
    /** @var array<string, array<array-key, array<array-key, mixed>>> */
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
            ->mapWithKeys(fn (Catalog $catalog): array => [$catalog->value => \count($this->load($catalog))])
            ->all();
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function records(Catalog $catalog): array
    {
        return array_values($this->load($catalog));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function find(Catalog $catalog, string $code): ?array
    {
        $record = $this->load($catalog)[$code] ?? null;

        return $record !== null && $this->accepts($catalog, $code, $record) ? $record : null;
    }

    /**
     * @param array<array-key, mixed> $record
     */
    private function accepts(Catalog $catalog, string $code, array $record): bool
    {
        return $catalog !== Catalog::Locations || ($record['level'] > 1 && Str::startsWith($code, Fiscal::COUNTRY));
    }

    /**
     * @return array<array-key, array<array-key, mixed>>
     */
    private function load(Catalog $catalog): array
    {
        return $this->loaded[$catalog->value] ??= $this->read($catalog);
    }

    /**
     * @return array<array-key, array<array-key, mixed>>
     */
    private function read(Catalog $catalog): array
    {
        try {
            $records = $this->filesystem->json($this->directory . '/' . $catalog->value . '.json', JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            throw new CatalogException($throwable instanceof JsonException ? 'catalogs.invalid' : 'catalogs.missing_or_unreadable', 'load', $throwable);
        }

        return collect($records)->map($this->record(...))->all();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function record(mixed $record, int|string $code): array
    {
        throw_unless(\is_array($record) && ($record['code'] ?? null) === (string) $code, CatalogException::class, 'catalogs.invalid', 'load');

        return $record;
    }
}
