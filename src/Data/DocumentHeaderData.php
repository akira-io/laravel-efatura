<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\FiscalDateCast;
use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\FiscalRules;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class DocumentHeaderData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[WithCast(FiscalDateCast::class, 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d', '')]
        public readonly CarbonImmutable $issueDate,
        #[WithCast(FiscalDateCast::class, 'H:i:s')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'H:i:s', '')]
        public readonly CarbonImmutable $issueTime,
        public readonly int $ledCode,
        public readonly ?string $serie = null,
        public readonly ?int $documentNumber = null,
        public readonly ?string $innerDocumentNumber = null,
        public readonly ?bool $isIsolatedAct = null,
        public readonly ?SelfBillingData $selfBilling = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'issueDate'           => ['required', new FiscalDate],
            'issueTime'           => ['required', new FiscalDate('H:i:s')],
            'ledCode'             => ['required', 'integer', 'between:1,99999'],
            'serie'               => ['nullable', 'string', 'max:20', 'regex:/\A[aA-zZ0-9]+(?:[_-][aA-zZ0-9]+)*\z/'],
            'documentNumber'      => ['nullable', 'integer', 'between:1,999999999'],
            'innerDocumentNumber' => ['nullable', ...FiscalRules::code()],
            'selfBilling'         => ['nullable'],
        ];
    }
}
