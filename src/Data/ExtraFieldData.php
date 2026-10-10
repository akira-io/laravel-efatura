<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\MayBeEmpty;
use Akira\Efatura\Rules\UnreservedFiscalField;
use Akira\Efatura\Support\FiscalRules;

final class ExtraFieldData extends FiscalData
{
    public function __construct(
        public readonly string $name,
        #[MayBeEmpty]
        public readonly string $value,
        public readonly ?string $namespace = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name'      => ['max:50', ...FiscalRules::xmlName(), new UnreservedFiscalField],
            'value'     => ['max:1000'],
            'namespace' => FiscalRules::xmlNamespace(),
        ];
    }
}
