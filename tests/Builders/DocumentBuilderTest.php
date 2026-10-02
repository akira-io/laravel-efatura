<?php

declare(strict_types=1);

use Akira\Efatura\Builders\DocumentBuilder;
use Akira\Efatura\Configuration\LoadEfaturaConfig;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\BuilderFixtures as B;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('creates canonical invoice drafts with clock defaults and exact totals', function (): void {
    $document = Efatura::invoice()->type(DocumentType::Invoice)->emitter(B::emitter(), 7)
        ->receiver(B::receiver())->line(F::line())->totals(F::totals())->build();

    expect($document)->toBeInstanceOf(ElectronicInvoiceData::class)
        ->and($document->header->ledCode)->toBe(7)
        ->and($document->header->issueDate->format('Y-m-d'))->toBe('2026-10-02')
        ->and($document->header->issueTime->format('H:i:s'))->toBe('12:00:00')
        ->and($document->header->documentNumber)->toBeNull()
        ->and((string) $document->totals->payableAmount->getAmount())->toBe('115.00000');
});

it('keeps a built document independent of later builder changes', function (): void {
    $builder  = Efatura::invoice()->emitter(B::emitter(), 7)->receiver(B::receiver())->line(F::line())->totals(F::totals());
    $document = $builder->build();

    $builder->ledCode(9);

    expect($document->header->ledCode)->toBe(7)
        ->and($builder->build()->header->ledCode)->toBe(9);
});

it('requires an emitter when the configuration has none', function (): void {
    config()->set('efatura.emitter');
    $draft = Efatura::invoice()->receiver(B::receiver())->line(F::line())->totals(F::totals());

    expect(fn (): DocumentData => $draft->build())->toFailValidationOn('emitter', 'The emitter field is required.');
});

it('loads the complete configured emitter into every draft', function (Closure $draft): void {
    config()->set('efatura.emitter', B::completeEmitterConfig());
    $manager  = resolve(EfaturaManager::class)->withConfig(resolve(LoadEfaturaConfig::class)());
    $document = $draft($manager)->receiver(B::receiver())->line(F::line())->totals(F::totals())->build();

    expect($document->emitter->taxId->value)->toBe('100200300')
        ->and($document->header->ledCode)->toBe(11)
        ->and($document->emitter->address->addressDetail)->toBe('Praia office')
        ->and($document->emitter->address->buildingFloor)->toBe('2')
        ->and($document->emitter->contacts->telefax)->toBe('1234568')
        ->and($document->emitter->contacts->website)->toBe('https://example.cv')
        ->and($document->lines)->toHaveCount(1);
})->with([
    'manager invoice'      => [fn (EfaturaManager $manager): DocumentBuilder => $manager->invoice()],
    'efatura from manager' => [fn (EfaturaManager $manager): DocumentBuilder => $manager->efatura()->invoice()],
]);

it('replaces the configured emitter without leaking its identity into the configuration', function (): void {
    config()->set('efatura.emitter', B::completeEmitterConfig());
    $config = resolve(LoadEfaturaConfig::class)();

    $document = resolve(EfaturaManager::class)->withConfig($config)->invoice()->emitter(B::alternateEmitter())->ledCode(22)
        ->receiver(B::receiver())->line(F::line())->totals(F::totals())->build();

    expect($document->emitter->taxId->value)->toBe('900800700')
        ->and($document->header->ledCode)->toBe(22)
        ->and($document->emitter->address->addressDetail)->toBe('Other office')
        ->and($document->emitter->contacts->telephone)->toBeNull()
        ->and($document->emitter->contacts->email)->toBe('other@example.cv')
        ->and($config->emitter->contacts->email)->toBe('default@example.cv')
        ->and($config->emitter->led)->toBe(11);
});

it('ignores presentation partials applied to supplied data after it was added', function (): void {
    $emitter = B::alternateEmitter();
    $line    = F::line();
    $draft   = Efatura::invoice()->emitter($emitter, 1)->receiver(B::receiver())->line($line)->totals(F::totals());

    $emitter->except('contacts');
    $line->except('taxes');
    $document = $draft->build();

    expect($emitter->toArray())->not->toHaveKey('contacts')
        ->and($line->toArray())->not->toHaveKey('taxes')
        ->and($document->emitter->contacts->email)->toBe('other@example.cv')
        ->and($document->lines[0]->taxes)->toHaveCount(1);
});

it('defers incomplete configured defaults until validation', function (): void {
    config()->set('efatura.emitter', ['tax_id' => '100200300', 'led' => '11', 'address' => ['country_code' => 'CV']]);
    $draft = resolve(EfaturaManager::class)->withConfig(resolve(LoadEfaturaConfig::class)())->invoice()
        ->receiver(B::receiver())->line(F::line())->totals(F::totals());

    expect(fn (): DocumentData => $draft->build())
        ->toFailValidationOn('emitter.name', 'The emitter.name field is required when emitter.reference is not present.');
});

it('keeps the configured LED unless one is given with or after the emitter', function (Closure $configure, int $ledCode): void {
    config()->set('efatura.emitter', ['tax_id' => '100200300', 'led' => '11', 'address' => ['country_code' => 'CV']]);
    $draft = resolve(EfaturaManager::class)->withConfig(resolve(LoadEfaturaConfig::class)())->invoice()
        ->receiver(B::receiver())->line(F::line())->totals(F::totals());

    expect($configure($draft)->build()->header->ledCode)->toBe($ledCode);
})->with([
    'emitter without LED'    => [fn (DocumentBuilder $draft): DocumentBuilder => $draft->emitter(B::emitter()), 11],
    'LED before the emitter' => [fn (DocumentBuilder $draft): DocumentBuilder => $draft->ledCode(22)->emitter(B::emitter()), 22],
    'LED after the emitter'  => [fn (DocumentBuilder $draft): DocumentBuilder => $draft->emitter(B::emitter())->ledCode(33), 33],
    'LED with the emitter'   => [fn (DocumentBuilder $draft): DocumentBuilder => $draft->emitter(B::emitter(), 44), 44],
]);

it('rehydrates a document issued days ago', function (): void {
    $payload = F::payload(['header' => ['issueDate' => '2026-09-29', 'issueTime' => '12:00:00', 'ledCode' => 1]]);

    expect(ElectronicInvoiceData::from($payload)->header->issueDate->format('Y-m-d'))->toBe('2026-09-29');
});

it('refuses to issue a document dated days ago through the builder', function (): void {
    $draft = B::issuance(['issueDate' => '2026-09-29', 'issueTime' => '12:00:00', 'ledCode' => 1]);

    expect(fn (): DocumentData => $draft->build())->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe(['header.issueDate' => ['The issue date and time are outside the permitted emission window.']]);
    });
});

it('assembles supplied data by value and ignores its presentation partials', function (): void {
    $receiver = B::receiver()->except('taxId');
    $line     = F::line()->except('taxes');
    $dueDate  = Date::parse('2026-10-31');
    $draft    = Efatura::invoice()->emitter(B::emitter(), 1)->receiver($receiver)->line($line)->totals(F::totals())->dueDate($dueDate);
    $dueDate->addDay();
    $document = $draft->build();

    expect($document->receiver->taxId->value)->toBe('900800700')
        ->and($document->lines[0]->taxes)->toHaveCount(1)
        ->and($document->toArray()['dueDate'])->toBe('2026-10-31')
        ->and($receiver->toArray())->not->toHaveKey('taxId');
});

it('exposes the payload of fiscal data without its presentation context', function (): void {
    $totals = F::totals();

    expect($totals->except('netTotalAmount')->toPayload())->toHaveKey('netTotalAmount')
        ->and($totals->toPayload())->toBe(F::totals()->toArray());
});
