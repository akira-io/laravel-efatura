<?php

declare(strict_types=1);

use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\InvoiceData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\BuilderFixtures as B;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});
afterEach(fn () => CarbonImmutable::setTestNow());

it('creates independent canonical invoice drafts with clock defaults and exact totals', function (): void {
    $builder = Efatura::invoice()->type(DocumentType::Invoice)
        ->emitter(B::emitter(), 7)
        ->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals());
    $document = $builder->validate();
    expect($document)->toBeInstanceOf(ElectronicInvoiceData::class)
        ->and($document->header->ledCode)->toBe(7)
        ->and($document->header->issueDate->format('Y-m-d'))->toBe('2026-10-02')
        ->and($document->header->issueTime->format('H:i:s'))->toBe('12:00:00')
        ->and($document->header->documentNumber)->toBeNull()
        ->and($document->totals->payableAmount->getAmount()->isEqualTo('115'))->toBeTrue();
    $builder->ledCode(9);
    expect($document->header->ledCode)->toBe(7)->and($builder->validate()->header->ledCode)->toBe(9);
    config()->set('efatura.emitter');
    $missingEmitter = Efatura::invoice()->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals());

    try {
        $missingEmitter->validate();
        test()->fail('Expected a missing emitter validation error');
    } catch (ValidationException $validationException) {
        expect(array_keys($validationException->errors()))->toContain('emitter');
    }
});

it('loads complete CV defaults and alternates emitters without leaking identity or presentation state', function (): void {
    config()->set('efatura.emitter', [
        'tax_id'  => '100200300', 'name' => 'Default emitter', 'led' => '11',
        'address' => ['country_code' => 'CV', 'address_detail' => 'Praia office', 'address_code' => 'CV111111111011110101',
            'state'                  => 'Santiago', 'region' => 'Praia', 'city' => 'Praia', 'street' => 'Main street', 'street_detail' => 'East',
            'building_name'          => 'Office', 'building_number' => '1', 'building_floor' => '2', 'postal_code' => '7600'],
        'contacts' => ['email' => 'default@example.cv', 'telephone' => '1234567', 'mobile' => '7654321', 'telefax' => '1234568', 'website' => 'https://example.cv'],
    ]);
    $config = resolve(LoadEfaturaConfig::class)();

    $manager = resolve(EfaturaManager::class)->withConfig($config);
    $party   = PartyData::from(['taxId' => ['value' => '900800700', 'countryCode' => 'CV'], 'name' => 'Other emitter',
        'address'                       => ['countryCode' => 'CV', 'addressDetail' => 'Other office', 'addressCode' => 'CV111111111011110101'], 'contacts' => ['email' => 'other@example.cv', 'mobilephone' => '9876543']]);
    $line  = F::line();
    $other = $manager->invoice()->emitter($party)->ledCode(22)->receiver(PartyData::from(F::payload()['receiver']))->line($line)->totals(F::totals());
    $party->exclude('contacts');
    $line->exclude('taxes');
    $b = $other->validate();
    foreach ([$manager->invoice(), $manager->efatura()->invoice()] as $draft) {
        $a = $draft->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals())->validate();
        expect($a->emitter->taxId->value)->toBe('100200300')->and($a->header->ledCode)->toBe(11)
            ->and($a->emitter->address->addressDetail)->toBe('Praia office')
            ->and($a->emitter->address->buildingFloor)->toBe('2')->and($a->emitter->contacts->telefax)->toBe('1234568')
            ->and($a->emitter->contacts->website)->toBe('https://example.cv')->and($a->lines)->toHaveCount(1);
    }

    expect($b->emitter->taxId->value)->toBe('900800700')->and($b->header->ledCode)->toBe(22)
        ->and($b->emitter->address->addressDetail)->toBe('Other office')->and($b->emitter->contacts->telephone)->toBeNull()
        ->and($b->emitter->contacts->email)->toBe('other@example.cv')->and($b->lines[0]->taxes)->toHaveCount(1)
        ->and($config->emitter->contacts->email)->toBe('default@example.cv')->and($config->emitter->led)->toBe('11');
});

it('defers incomplete defaults until validation and clears LED on explicit emitter replacement', function (): void {
    config()->set('efatura.emitter', ['tax_id' => '100200300', 'led' => '11', 'address' => ['country_code' => 'CV']]);
    $manager = new EfaturaManager(resolve(LoadEfaturaConfig::class)());
    $draft   = $manager->invoice()->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals());
    expect(fn (): InvoiceData => $draft->validate())->toThrow(ValidationException::class);
    $draft->emitter(B::emitter());
    expect(fn (): InvoiceData => $draft->validate())->toThrow(ValidationException::class);
    expect($draft->ledCode(22)->validate()->header->ledCode)->toBe(22);

    $draft->emitter(B::emitter());
    expect(fn (): InvoiceData => $draft->validate())->toThrow(ValidationException::class);
});
