<?php

declare(strict_types=1);

namespace Akira\Efatura\Rules;

use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\ValidationPayload;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final readonly class ChronologicalOrder implements ValidationRule
{
    private function __construct(private ?string $start, private ?string $end, private string $startAttribute) {}

    /**
     * @param non-empty-array<string, string> $start
     * @param non-empty-array<string, string> $end
     */
    public static function between(ValidationContext $context, array $start, array $end): self
    {
        return new self(self::moment($context, $start), self::moment($context, $end), FieldPath::of($context, array_key_last($start)));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->start !== null && $this->end !== null && $this->end < $this->start) {
            $fail('validation.after_or_equal')->translate(['date' => $this->startAttribute]);
        }
    }

    /**
     * @param array<string, string> $formats
     */
    private static function moment(ValidationContext $context, array $formats): ?string
    {
        $parts = collect($formats)->map(static function (string $format, string $field) use ($context): ?string {
            $value = ValidationPayload::value($context, $field);

            return $value instanceof CarbonInterface ? Fiscal::local($value)->format($format) : (\is_string($value) ? $value : null);
        });

        return $parts->contains(null) ? null : $parts->implode('T');
    }
}
