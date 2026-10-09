<?php

declare(strict_types=1);

namespace Akira\Efatura\Tools\Catalogs;

use PhpOffice\PhpSpreadsheet\IOFactory;
use UnexpectedValueException;

final class Workbook
{
    /**
     * @return array<int, array<int, float|int|string>>
     */
    public function rows(string $path, string $sheet): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheet]);

        $rows = $reader->load($path)->getSheetByNameOrThrow($sheet)->toArray(null, false, false, false);

        return collect($rows)
            ->map(fn (array $row): array => array_map($this->clean(...), array_values($row)))
            ->values()
            ->all();
    }

    private function clean(mixed $value): float|int|string
    {
        return match (true) {
            $value === null                                        => '',
            \is_float($value) && floor($value) === $value          => (int) $value,
            \is_string($value), \is_int($value), \is_float($value) => $value,
            default                                                => $this->unexpected($value),
        };
    }

    private function unexpected(mixed $value): never
    {
        throw new UnexpectedValueException('Unexpected worksheet value: ' . get_debug_type($value));
    }
}
