<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\PartyReference;
use Akira\Efatura\Support\FiscalRules;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class PartyData extends Data
{
    use ValidatesFiscalFields;

    public function __construct(
        public readonly ?TaxIdData $taxId = null,
        public readonly ?string $name = null,
        public readonly ?AddressData $address = null,
        public readonly ?ContactsData $contacts = null,
        public readonly ?PartyReference $reference = null,
    ) {
        $this->validateFiscalFields(self::rules());
    }

    public function validateEmitter(): void
    {
        $this->validateFiscalFields(['taxId' => ['required'], 'contacts' => ['required'], 'reference' => ['prohibited']]);
        $this->contacts?->validateEmitter();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'taxId'     => ['required_without:reference'],
            'name'      => ['nullable', 'required_without:reference', ...FiscalRules::text(3, 150)],
            'reference' => ['nullable', Rule::enum(PartyReference::class), 'prohibits:taxId,name,address,contacts'],
        ];
    }
}
