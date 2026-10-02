<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use BackedEnum;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ValidationPayload
{
    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum> $enum
     * @return TEnum|null
     */
    public static function enum(ValidationContext $context, string $field, string $enum, ?BackedEnum $default = null): ?BackedEnum
    {
        $value = self::value($context, $field);

        if ($value instanceof $enum) {
            return $value;
        }

        if ($value === null) {
            return $default instanceof $enum ? $default : null;
        }

        return collect($enum::cases())->first(static fn (BackedEnum $case): bool => \is_scalar($value) && (string) $case->value === (string) $value);
    }

    public static function string(ValidationContext $context, string $field): ?string
    {
        $value = self::value($context, $field);

        return \is_string($value) ? $value : null;
    }

    public static function isTrue(ValidationContext $context, string $field): bool
    {
        return \in_array(self::value($context, $field), [true, 1, '1'], true);
    }

    public static function value(ValidationContext $context, string $field): mixed
    {
        return \is_array($context->payload) ? Arr::get($context->payload, $field) : null;
    }
}
