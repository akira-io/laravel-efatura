<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

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
            'authorizationId'   => ['regex:/\A\w{8}-(?:\w{4}-){3}\w{12}\z/u'],
            'authorizationCode' => ['regex:/\A[0-9]{4,10}\z/'],
        ];
    }
}
