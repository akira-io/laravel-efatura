<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\FiscalRules;

final class PaymentTermsData extends FiscalData
{
    public function __construct(
        public readonly string $note,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return ['note' => FiscalRules::text(10, 500)];
    }
}
