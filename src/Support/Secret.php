<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Exceptions\DefinitionException;
use Closure;
use JsonSerializable;
use SensitiveParameter;

final readonly class Secret implements JsonSerializable
{
    /** @var Closure(): string */
    private Closure $reveal;

    public function __construct(#[SensitiveParameter] string $value)
    {
        $this->reveal = static fn (): string => $value;
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
        return ($this->reveal)();
    }

    public function jsonSerialize(): string
    {
        return '[redacted]';
    }
}
