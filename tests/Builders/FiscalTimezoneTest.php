<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ValidateIssueDateAction;
use Akira\Efatura\Data\ContingencyData;
use Akira\Efatura\Data\DeliveryData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\DurationData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Enums\EventType;
use Akira\Efatura\Facades\Efatura;
use Akira\Efatura\Tests\Support\BuilderFixtures as B;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Carbon\FactoryImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Psr\Clock\ClockInterface;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    $this->originalTimezone = date_default_timezone_get();
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');
    CarbonImmutable::setTestNow('2026-10-03T00:30:00Z');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    date_default_timezone_set($this->originalTimezone);
});

it('converts the issuance instant to Cape Verde time and keeps calendar dates as given', function (): void {
    $calendarDay = CarbonImmutable::parse('2026-10-02');
    $document    = Efatura::invoice()->emitter(B::emitter(), 1)->issuedAt(CarbonImmutable::parse('2026-10-03 00:30', 'UTC'))
        ->dueDate($calendarDay)->taxPointDate($calendarDay)
        ->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals())->build();

    expect($document->toArray()['header'])->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '23:30:00'])
        ->and($document->toArray())->toMatchArray(['dueDate' => '2026-10-02', 'taxPointDate' => '2026-10-02']);
});

it('formats event dates in Cape Verde time', function (): void {
    $event = Efatura::event()->type(EventType::FiscalDocumentCancellation)->emitter(new TaxIdData('100200300', 'CV'))
        ->issuedAt(CarbonImmutable::parse('2026-10-03 00:30', 'UTC'))->reason('Document cancelled by emitter')
        ->iud('CV1261002100200300' . str_repeat('0', 27))->build();

    expect($event->toArray()['issueDateTime'])->toBe('2026-10-02T23:30:00');
});

it('defaults builder dates from a host timezone clock in Cape Verde time', function (): void {
    $this->app->instance(ClockInterface::class, new FactoryImmutable(['timezone' => 'UTC']));
    $document = Efatura::efatura()->invoice()->emitter(B::emitter(), 1)
        ->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals())->build();
    $event = Efatura::efatura()->event()->type(EventType::FiscalDocumentCancellation)->emitter(new TaxIdData('100200300', 'CV'))
        ->reason('Document cancelled by emitter')->iud('CV1261002100200300' . str_repeat('0', 27))->build();

    expect($document->toArray()['header'])->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '23:30:00'])
        ->and($event->toArray()['issueDateTime'])->toBe('2026-10-02T23:30:00');
});

it('normalises mutable carbon instances given to a fiscal date cast', function (string $method): void {
    $moment = Date::parse('2026-10-03 00:30', 'UTC');
    $header = DocumentHeaderData::$method(['issueDate' => $moment, 'issueTime' => $moment, 'ledCode' => 1]);

    expect($header->toArray())->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '23:30:00'])
        ->and($header->issueDate->toDateTimeString())->toBe('2026-10-02 00:00:00')
        ->and($header->issueDate->timezoneName)->toBe('Atlantic/Cape_Verde')
        ->and($header->issueTime->format('H:i:s'))->toBe('23:30:00');
})->with(['from', 'validateAndCreate']);

it('transforms immutable carbon instances in Cape Verde time', function (): void {
    $moment = CarbonImmutable::parse('2026-10-03 00:30', 'UTC');

    expect(DocumentHeaderData::from(['issueDate' => $moment, 'issueTime' => $moment, 'ledCode' => 1])->toArray())
        ->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '23:30:00']);
});

it('transforms constructed carbon instances in Cape Verde time', function (): void {
    $moment = CarbonImmutable::parse('2026-10-03 00:30', 'UTC');

    expect(new DocumentHeaderData($moment, $moment, 1)->toArray())->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '23:30:00']);
});

it('converts the contingency issuance instant to Cape Verde time', function (string $method): void {
    $moment      = CarbonImmutable::parse('2026-10-03 00:30', 'UTC');
    $contingency = ContingencyData::$method(['issueDate' => $moment, 'issueTime' => $moment, 'reasonTypeCode' => ContingencyReason::Other, 'ledCode' => 1, 'reasonDescription' => 'Network outage at the store']);

    expect($contingency->toArray())->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '23:30:00'])
        ->and(new ContingencyData($moment, ContingencyReason::Other, 1, issueTime: $moment)->toArray())->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '23:30:00']);
})->with(['from', 'validateAndCreate']);

it('keeps calendar date fields as given on a UTC host', function (): void {
    $calendarDay = CarbonImmutable::parse('2026-10-02');
    $payment     = ['paymentMeansCode' => '10', 'paymentDate' => $calendarDay, 'paymentAmount' => '115'];
    $delivery    = DeliveryData::from(['deliveryDate' => Date::parse('2026-10-02'), 'address' => ['countryCode' => 'PT', 'addressDetail' => 'Lisbon']]);
    $duration    = new DurationData($calendarDay, CarbonImmutable::parse('09:00:00'));

    expect(ReceiptInvoiceData::from(F::payload(['payments' => ['payments' => [$payment]]]))->toArray()['payments']['payments'][0]['paymentDate'])->toBe('2026-10-02')
        ->and($delivery->toArray()['deliveryDate'])->toBe('2026-10-02')
        ->and($delivery->deliveryDate->timezoneName)->toBe('Atlantic/Cape_Verde')
        ->and($duration->toArray())->toMatchArray(['startDate' => '2026-10-02', 'startTime' => '09:00:00']);
});

it('compares a calendar tax point date with the Cape Verde issue day', function (): void {
    $taxPoint = CarbonImmutable::parse('2026-10-03 00:30', 'Pacific/Kiritimati');

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['taxPointDate' => $taxPoint])))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toBe(['taxPointDate' => ['The tax point date cannot be later than the issue date.']]);
        });
});

it('places the issue time on the Cape Verde clock when checking the emission window', function (): void {
    CarbonImmutable::setTestNow('2026-10-03T02:00:00Z');
    $header = new DocumentHeaderData(CarbonImmutable::parse('2026-10-02', 'Atlantic/Cape_Verde'), CarbonImmutable::parse('2026-10-03 00:30', 'UTC'), 1);

    resolve(ValidateIssueDateAction::class)->handle($header, EmissionMode::Online);

    expect($header->toArray())->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '23:30:00']);
});
