<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Spatie\LaravelData\Data;

final class SelfBillingData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $authorizationId,
        public readonly string $authorizationCode,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'authorizationId'   => ['required', 'regex:/\A\w{8}-(?:\w{4}-){3}\w{12}\z/u'],
            'authorizationCode' => ['required', 'regex:/\A[0-9]{4,10}\z/'],
        ];
    }
}
