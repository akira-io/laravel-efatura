<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\FiscalDateCast;
use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\FiscalRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class EventData extends Data
{
    use ValidatesFiscalFields;

    /**
     * @param list<string> $iuds
     */
    public function __construct(
        public readonly EventType $eventTypeCode,
        public readonly TaxIdData $emitterTaxId,
        #[WithCast(FiscalDateCast::class, 'Y-m-d\TH:i:s')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d\TH:i:s', '')]
        public readonly CarbonImmutable $issueDateTime,
        public readonly string $issueReasonDescription,
        public readonly array $iuds = [],
        public readonly ?EventNumberRangeData $numberRange = null,
        public readonly ?EmissionContextData $emission = null,
    ) {
        $this->validateFiscalFields(self::rules());
        Validator::make(['countryCode' => $emitterTaxId->countryCode, 'issueDate' => $issueDateTime->format('Y-m-d')], ['countryCode' => ['required', 'in:CV'], 'issueDate' => [new FiscalDate]])->validate();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'issueDateTime'          => ['required', new FiscalDate('Y-m-d\TH:i:s')],
            'issueReasonDescription' => ['required', ...FiscalRules::text(10, 500)],
            'iuds'                   => ['array', 'list', 'required_if:eventTypeCode,FDC', 'prohibited_if:eventTypeCode,UDN'],
            'iuds.*'                 => ['required', 'string', 'distinct:strict', 'regex:/\ACV[0-9][0-9]{2}(?:0[1-9]|1[012])(?:0[1-9]|[12][0-9]|3[01])[1-9][0-9]{35}\z/'],
            'numberRange'            => ['nullable', 'required_if:eventTypeCode,UDN', 'prohibited_if:eventTypeCode,FDC'],
            'emission'               => ['nullable'],
        ];
    }
}
