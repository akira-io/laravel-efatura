<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;

final class PaymentTermsData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $note,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['note' => ['required', ...FiscalRules::text(10, 500)]];
    }
}
