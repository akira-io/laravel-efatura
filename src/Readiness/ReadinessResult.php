<?php

declare(strict_types=1);

namespace Akira\Efatura\Readiness;

use Akira\Efatura\Enums\ReadinessCheck;
use Akira\Efatura\Enums\ReadinessStatus;
use Akira\Efatura\Exceptions\EfaturaException;
use Throwable;

final readonly class ReadinessResult
{
    private const string UNEXPECTED_FAILURE = 'readiness.unexpected_failure';

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public function __construct(
        public ReadinessCheck $check,
        public ReadinessStatus $status,
        public string $code,
        public ?string $field = null,
        public array $context = [],
        public bool $required = true,
    ) {}

    public function blocks(): bool
    {
        return $this->status === ReadinessStatus::Failed || ($this->status === ReadinessStatus::Skipped && $this->required);
    }

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public static function passed(ReadinessCheck $check, string $code, array $context = []): self
    {
        return new self($check, ReadinessStatus::Passed, $code, null, $context);
    }

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public static function warning(ReadinessCheck $check, string $code, ?string $field = null, array $context = []): self
    {
        return new self($check, ReadinessStatus::Warning, $code, $field, $context);
    }

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public static function failed(ReadinessCheck $check, string $code, ?string $field = null, array $context = []): self
    {
        return new self($check, ReadinessStatus::Failed, $code, $field, $context);
    }

    public static function failure(ReadinessCheck $check, Throwable $throwable): self
    {
        return $throwable instanceof EfaturaException
            ? self::failed($check, $throwable->errorCode, $throwable->field)
            : self::failed($check, self::UNEXPECTED_FAILURE);
    }

    public static function skipped(ReadinessCheck $check, bool $required): self
    {
        return new self($check, ReadinessStatus::Skipped, 'readiness.skipped', required: $required);
    }
}
