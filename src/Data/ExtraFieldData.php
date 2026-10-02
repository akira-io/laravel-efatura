<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Data\Attributes\MayBeEmpty;
use Akira\Efatura\Rules\NotBlank;
use Akira\Efatura\Rules\UnreservedFiscalField;
use Akira\Efatura\Support\Fiscal;

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
            'name'      => ['regex:/\A[\p{L}_][\p{L}\p{N}_.-]*\z/u', new UnreservedFiscalField],
            'namespace' => [new NotBlank, 'regex:/\A[a-zA-Z][a-zA-Z0-9+.-]*:[^\s]+\z/', 'not_in:' . Fiscal::XML_NAMESPACE],
        ];
    }
}
