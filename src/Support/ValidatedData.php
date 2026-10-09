<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Data\FiscalData;
use WeakMap;

final class ValidatedData
{
    public const string KEY = 'efatura:validated';

    /** @var WeakMap<FiscalData, true>|null */
    private static ?WeakMap $instances = null;

    private static ?self $marker = null;

    private function __construct() {}

    /**
     * @template TData of FiscalData
     *
     * @param  TData $data
     * @return TData
     */
    public static function remember(FiscalData $data): FiscalData
    {
        self::instances()[$data] = true;

        return $data;
    }

    /**
     * @param  array<array-key, mixed> $payload
     * @return array<array-key, mixed>
     */
    public static function mark(FiscalData $data, array $payload): array
    {
        if (! self::instances()->offsetExists($data)) {
            return $payload;
        }

        return [...$payload, self::KEY => self::marker()];
    }

    public static function isMarked(mixed $payload): bool
    {
        return \is_array($payload) && ($payload[self::KEY] ?? null) === self::marker();
    }

    /**
     * @return WeakMap<FiscalData, true>
     */
    private static function instances(): WeakMap
    {
        return self::$instances ??= new WeakMap;
    }

    private static function marker(): self
    {
        return self::$marker ??= new self;
    }
}
