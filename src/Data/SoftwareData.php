<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Data;

final class SoftwareData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $version,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'code'    => ['required', 'regex:/\A[A-Z0-9]{1,10}\z/'],
            'name'    => ['required', ...FiscalRules::text(3, 150)],
            'version' => ['required', ...FiscalRules::text(1, 50)],
        ];
    }
}
