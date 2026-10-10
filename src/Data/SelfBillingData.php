<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\FiscalRules;

final class SelfBillingData extends FiscalData
{
    public function __construct(
        public readonly string $authorizationId,
        public readonly string $authorizationCode,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'authorizationId'   => FiscalRules::uuid(),
            'authorizationCode' => ['regex:/\A[0-9]{4,10}\z/'],
        ];
    }
}
