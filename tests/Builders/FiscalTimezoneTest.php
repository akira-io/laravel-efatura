<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ValidateIssueDateAction;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\TaxIdData;
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

it('formats builder dates in Cape Verde time whatever the host timezone', function (): void {
    $moment   = CarbonImmutable::parse('2026-10-03 00:30', 'UTC');
    $document = Efatura::invoice()->emitter(B::emitter(), 1)->issuedAt($moment)->dueDate($moment)->taxPointDate($moment)
        ->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals())->validate();

    expect($document->toArray()['header'])->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '23:30:00'])
        ->and($document->toArray())->toMatchArray(['dueDate' => '2026-10-02', 'taxPointDate' => '2026-10-02']);
});

it('formats event dates in Cape Verde time', function (): void {
    $event = Efatura::event()->type(EventType::FiscalDocumentCancellation)->emitter(new TaxIdData('100200300', 'CV'))
        ->issuedAt(CarbonImmutable::parse('2026-10-03 00:30', 'UTC'))->reason('Document cancelled by emitter')
        ->iud('CV1261002100200300' . str_repeat('0', 27))->validate();

    expect($event->toArray()['issueDateTime'])->toBe('2026-10-02T23:30:00');
});

it('defaults builder dates from a host timezone clock in Cape Verde time', function (): void {
    $this->app->instance(ClockInterface::class, new FactoryImmutable(['timezone' => 'UTC']));
    $document = Efatura::efatura()->invoice()->emitter(B::emitter(), 1)
        ->receiver(PartyData::from(F::payload()['receiver']))->line(F::line())->totals(F::totals())->validate();
    $event = Efatura::efatura()->event()->type(EventType::FiscalDocumentCancellation)->emitter(new TaxIdData('100200300', 'CV'))
        ->reason('Document cancelled by emitter')->iud('CV1261002100200300' . str_repeat('0', 27))->validate();

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

it('compares tax point and payment dates on the Cape Verde calendar', function (): void {
    $sameDay = CarbonImmutable::parse('2026-10-03 00:30', 'UTC');
    $payment = ['paymentMeansCode' => '10', 'paymentDate' => $sameDay, 'paymentAmount' => '115'];

    expect(ElectronicInvoiceData::from(F::payload(['taxPointDate' => $sameDay]))->toArray()['taxPointDate'])->toBe('2026-10-02')
        ->and(ReceiptInvoiceData::from(F::payload(['payments' => ['payments' => [$payment]]]))->toArray()['payments']['payments'][0]['paymentDate'])->toBe('2026-10-02');
});

it('places the issue time on the Cape Verde clock when checking the emission window', function (): void {
    CarbonImmutable::setTestNow('2026-10-03T02:00:00Z');
    $header = new DocumentHeaderData(CarbonImmutable::parse('2026-10-02', 'Atlantic/Cape_Verde'), CarbonImmutable::parse('2026-10-03 00:30', 'UTC'), 1);

    expect(fn () => resolve(ValidateIssueDateAction::class)->handle($header, EmissionMode::Online))->not->toThrow(ValidationException::class);
});
