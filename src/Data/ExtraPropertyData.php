<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Support\FiscalRules;

final class ExtraPropertyData extends FiscalData
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return ['name' => FiscalRules::code(), 'value' => ['max:1000']];
    }
}
