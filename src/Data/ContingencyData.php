<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Casts\FiscalDateCast;
use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Rules\FiscalDate;
use Akira\Efatura\Support\FiscalRules;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class ContingencyData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        #[WithCast(FiscalDateCast::class, 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d', '')]
        public readonly CarbonImmutable $issueDate,
        public readonly ContingencyReason $reasonTypeCode,
        public readonly int $ledCode,
        public readonly ?string $iuc = null,
        #[WithCast(FiscalDateCast::class, 'H:i:s')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'H:i:s', '')]
        public readonly ?CarbonImmutable $issueTime = null,
        public readonly ?string $reasonDescription = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'issueDate'         => ['required', new FiscalDate],
            'ledCode'           => ['required', 'integer', 'between:1,99999'],
            'iuc'               => ['nullable', 'regex:/\A[0-9]{4}\/[0-9]+\z/'],
            'issueTime'         => ['nullable', new FiscalDate('H:i:s')],
            'reasonDescription' => ['nullable', 'required_if:reasonTypeCode,0', ...FiscalRules::text(10, 500)],
        ];
    }
}
