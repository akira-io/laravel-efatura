<?php

declare(strict_types=1);

namespace Akira\Efatura\Data;

use Akira\Efatura\Concerns\ValidatesFiscalFields;
use Akira\Efatura\Enums\PartyReference;
use Akira\Efatura\Support\FiscalRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

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
        Validator::make(['taxId' => ['countryCode' => $this->taxId?->countryCode]], ['taxId.countryCode' => ['required', 'in:CV']])->validate();
        $this->contacts?->validateEmitter();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $field = static fn (string $name): string => $context?->path->property($name)->get() ?? $name;

        return [
            'taxId'     => ['required_without:' . $field('reference')],
            'name'      => ['nullable', 'required_without:' . $field('reference'), ...FiscalRules::text(3, 150)],
            'reference' => ['nullable', Rule::enum(PartyReference::class), 'prohibits:' . $field('taxId') . ',' . $field('name') . ',' . $field('address') . ',' . $field('contacts')],
        ];
    }
}
