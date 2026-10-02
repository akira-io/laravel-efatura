<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Data\Attributes\FiscalDateFormat;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelData\Data;

final class EventData extends Data
{
    use ValidatesFiscalFields;

    /**
     * @param list<string> $iuds
     */
    public function __construct(
        public readonly EventType $eventTypeCode,
        public readonly TaxIdData $emitterTaxId,
        #[FiscalDateFormat(Fiscal::DATE_TIME_FORMAT)]
        public readonly CarbonImmutable $issueDateTime,
        public readonly string $issueReasonDescription,
        public readonly array $iuds = [],
        public readonly ?EventNumberRangeData $numberRange = null,
        public readonly ?EmissionContextData $emission = null,
    ) {
        $this->validateFiscalFields(self::rules());
        Validator::make(['countryCode' => $emitterTaxId->countryCode, 'issueDate' => $issueDateTime->format(Fiscal::DATE_FORMAT)], ['countryCode' => ['required', 'in:' . Fiscal::COUNTRY], 'issueDate' => [new FiscalDate]])->validate();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'issueDateTime'          => ['required', new FiscalDate(Fiscal::DATE_TIME_FORMAT)],
            'issueReasonDescription' => ['required', ...FiscalRules::text(10, 500)],
            'iuds'                   => ['array', 'list', 'required_if:eventTypeCode,FDC', 'prohibited_if:eventTypeCode,UDN'],
            'iuds.*'                 => ['required', 'distinct:strict', ...FiscalRules::iud()],
            'numberRange'            => ['nullable', 'required_if:eventTypeCode,UDN', 'prohibited_if:eventTypeCode,FDC'],
            'emission'               => ['nullable'],
        ];
    }
}
