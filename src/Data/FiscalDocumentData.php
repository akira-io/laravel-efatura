<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Support\ValidationPayload;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Support\Validation\ValidationContext;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

final class FiscalDocumentData extends FiscalData
{
    public function __construct(
        public readonly string $value,
        public readonly ?bool $isOldDocument = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $value = ValidationPayload::string($context, 'value') ?? '';
        $isOld = ValidationPayload::value($context, 'isOldDocument');
        $isOld = \is_bool($isOld) ? $isOld : filter_var($isOld, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $isCurrent = Str::startsWith($value, Fiscal::COUNTRY);

        return [
            'value'         => FiscalRules::fiscalDocumentReference(),
            'isOldDocument' => [Rule::prohibitedIf($isOld !== null && $isOld === $isCurrent)],
        ];
    }
}
