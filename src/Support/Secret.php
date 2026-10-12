<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Exceptions\DefinitionException;
use JsonSerializable;
use SensitiveParameter;
use WeakMap;

final class Secret implements JsonSerializable
{
    /** @var WeakMap<self, string>|null */
    private static ?WeakMap $values = null;

    public function __construct(#[SensitiveParameter] string $value)
    {
        self::$values ??= new WeakMap;
        self::$values[$this] = $value;
    }

    public function __clone(): void
    {
        throw DefinitionException::secretSerialization();
    }

    /**
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }

    /**
     * @return array<never>
     */
    public function __serialize(): array
    {
        throw DefinitionException::secretSerialization();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw DefinitionException::secretSerialization();
    }

    public static function from(#[SensitiveParameter] ?string $value): ?self
    {
        return $value === null ? null : new self($value);
    }

    public function reveal(): string
    {
        return self::$values[$this] ?? throw DefinitionException::secretSerialization();
    }

    public function jsonSerialize(): string
    {
        return '[redacted]';
    }
}
