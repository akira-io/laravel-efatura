<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Enums\PartyReference;
use Akira\Efatura\Support\FieldPath;
use Akira\Efatura\Support\FiscalRules;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PartyData extends FiscalData
{
    public function __construct(
        public readonly ?TaxIdData $taxId = null,
        public readonly ?string $name = null,
        public readonly ?AddressData $address = null,
        public readonly ?ContactsData $contacts = null,
        public readonly ?PartyReference $reference = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        if (! \is_array($context->payload) || $context->payload === []) {
            return [];
        }

        return [
            'taxId'     => ['required_without:' . FieldPath::of($context, 'reference')],
            'name'      => ['required_without:' . FieldPath::of($context, 'reference'), ...FiscalRules::text(3, 150)],
            'reference' => ['prohibits:' . FieldPath::list($context, 'taxId', 'name', 'address', 'contacts')],
        ];
    }
}
