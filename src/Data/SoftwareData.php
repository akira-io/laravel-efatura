<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\FiscalRules;

final class SoftwareData extends FiscalData
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $version,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'code'    => ['regex:/\A[A-Z0-9]{1,10}\z/'],
            'name'    => FiscalRules::text(3, 150),
            'version' => FiscalRules::text(1, 50),
        ];
    }
}
