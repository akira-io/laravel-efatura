<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Akira\Efatura\Support\ValidationPayload;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class EventData extends FiscalData
{
    /**
     * @param list<string> $iuds
     */
    public function __construct(
        #[MapName('eventTypeCode')]
        public readonly EventType $eventType,
        public readonly TaxIdData $emitterTaxId,
        #[FiscalDateFormat(Fiscal::DATE_TIME_FORMAT, instant: true)]
        public readonly CarbonImmutable $issueDateTime,
        public readonly string $issueReasonDescription,
        #[RequiredIf('eventTypeCode', EventType::FiscalDocumentCancellation)]
        public readonly array $iuds = [],
        public readonly ?EventNumberRangeData $numberRange = null,
        public readonly ?EmissionContextData $emission = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $type = ValidationPayload::enum($context, 'eventTypeCode', EventType::class);

        return [
            'emitterTaxId.countryCode' => ['in:' . Fiscal::COUNTRY],
            'issueDateTime'            => [new FiscalDate(Fiscal::DATE_TIME_FORMAT, instant: true), 'after_or_equal:' . Fiscal::EARLIEST_DATE],
            'issueReasonDescription'   => FiscalRules::text(10, 500),
            'iuds'                     => ['list', Rule::prohibitedIf($type === EventType::UnusedDocumentNumber)],
            'iuds.*'                   => ['required', 'distinct:strict', ...FiscalRules::iud()],
            'numberRange'              => [
                Rule::requiredIf($type === EventType::UnusedDocumentNumber),
                Rule::prohibitedIf($type === EventType::FiscalDocumentCancellation),
            ],
        ];
    }
}
