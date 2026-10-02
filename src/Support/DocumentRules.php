<?php

declare(strict_types=1);

namespace Akira\Efatura\Support;

use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\LineType;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DocumentRules
{
    /**
     * @return list<string>
     */
    public static function requiredList(): array
    {
        return ['required', 'array', 'min:1'];
    }

    /**
     * @return list<Enum>
     */
    public static function issueReason(DocumentType $type): array
    {
        return [Rule::enum(IssueReason::class)->only(IssueReason::allowedFor($type))];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function settledPayments(ValidationContext $context): array
    {
        if (! \is_array(ValidationPayload::value($context, 'payments'))) {
            return [];
        }

        return [
            'payments.payments'               => self::requiredList(),
            'payments.paymentDueDate'         => ['prohibited'],
            'payments.paymentTerms'           => ['prohibited'],
            'payments.payeeFinancialAccounts' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function lines(ValidationContext $context, DocumentType $type): array
    {
        $lines     = self::linePayloads($context);
        $lineTypes = $lines->filter(static fn (array $line): bool => \is_scalar($line['id'] ?? null))
            ->keyBy(static fn (array $line): string => (string) $line['id'])
            ->map(static fn (array $line): mixed => $line['lineTypeCode'] ?? LineType::Normal->value);
        $normalIds = $lineTypes->filter(static fn (mixed $lineType): bool => $lineType === LineType::Normal->value)->keys()->all();

        $rules = [
            'lines.*.id'              => ['nullable', 'distinct:strict'],
            'lines.*.lineReferenceId' => ['nullable', Rule::in($lineTypes->keys()->all())],
            ...$lines->filter(static fn (array $line): bool => ($line['lineTypeCode'] ?? null) === LineType::Charge->value)
                ->mapWithKeys(static fn (array $line, int|string $index): array => ['lines.' . $index . '.lineReferenceId' => ['required', Rule::in($normalIds)]])
                ->all(),
        ];

        if ($type->requiresLinePricing()) {
            $rules = [...$rules, 'lines.*.price' => ['required'], 'lines.*.priceExtension' => ['required'], 'lines.*.netTotal' => ['required']];
        }

        if ($type->requiresLineTaxes()) {
            $rules['lines.*.taxes'] = ['required'];
        }

        return $rules;
    }

    /**
     * @return Collection<int|string, array<array-key, mixed>>
     */
    private static function linePayloads(ValidationContext $context): Collection
    {
        $lines = ValidationPayload::value($context, 'lines');

        return collect(\is_array($lines) ? $lines : [])->filter(static fn (mixed $line): bool => \is_array($line));
    }
}
