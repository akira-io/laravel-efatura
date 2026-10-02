<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Data\TransportRouteData;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Enums\TransportDocumentType;
use Akira\Efatura\Enums\TransportReceiverType;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});
afterEach(fn () => CarbonImmutable::setTestNow());

it('accepts an omitted return receiver while retaining supplied parties and self billing requirements', function (string $factory, bool $receiver, bool $selfBilling): void {
    $payload = F::payload(['issueReasonCode' => '2', 'references' => F::references()]);
    if (! $receiver) {
        unset($payload['receiver']);
    }

    if ($selfBilling) {
        $payload['header']['selfBilling'] = ['authorizationId' => '12345678-1234-1234-1234-123456789abc', 'authorizationCode' => '1234'];
    }

    $create = function () use ($factory, $payload): ReturnNoteData {
        if ($factory !== 'constructor') {
            return ReturnNoteData::$factory($payload);
        }

        $arguments = [
            'header'     => DocumentHeaderData::from($payload['header']), 'emitter' => PartyData::from($payload['emitter']),
            'lines'      => [F::line()], 'totals' => F::totals(), 'issueReasonCode' => IssueReason::from('2'),
            'references' => [ReferenceData::from(F::references()[0])],
        ];
        if (isset($payload['receiver'])) {
            $arguments['receiver'] = PartyData::from($payload['receiver']);
        }

        return new ReturnNoteData(...$arguments);
    };
    if ($selfBilling && ! $receiver) {
        expect($create)->toThrow(ValidationException::class);
    } else {
        expect($create()->receiver?->taxId?->countryCode)->toBe($receiver ? 'CV' : null);
    }
})->with(['constructor', 'from', 'validateAndCreate'])->with([[false, false], [true, false], [false, true], [true, true]]);

it('requires CV identity only for transport taxpayer receivers and resolves emitter references', function (string $factory, string $type, array $receiver, bool $valid): void {
    $payload = F::payload(['receiver' => $receiver, 'receiverTypeCode' => $type, 'transportDocumentTypeCode' => '2',
        'transportServiceProvider'    => ['reference' => 'EP'], 'transportRoute' => F::route()]);
    unset($payload['totals']);
    $create = fn (): TransportDocumentData => $factory === 'constructor'
        ? new TransportDocumentData(
            header: DocumentHeaderData::from($payload['header']),
            emitter: PartyData::from($payload['emitter']),
            transportDocumentTypeCode: TransportDocumentType::from('2'),
            transportServiceProvider: PartyData::from(['reference' => 'EP']),
            lines: [F::line()],
            transportRoute: TransportRouteData::from(F::route()),
            receiverTypeCode: TransportReceiverType::from($type),
            receiver: PartyData::from($receiver),
        ) : TransportDocumentData::$factory($payload);
    if ($valid) {
        expect($create()->receiver->toArray())->toBe(PartyData::from($receiver)->toArray());
    } else {
        try {
            $create();
            test()->fail('Foreign transport taxpayer accepted');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('receiver.taxId.countryCode');
        }
    }
})->with(['constructor', 'from', 'validateAndCreate'])->with([
    ['1', ['taxId' => ['value' => '123456789', 'countryCode' => 'PT'], 'name' => 'Foreign buyer'], false],
    ['1', ['taxId' => ['value' => '123456789', 'countryCode' => 'CV'], 'name' => 'Local buyer'], true],
    ['1', ['reference' => 'EP'], true],
    ['2', ['taxId' => ['value' => '123456789', 'countryCode' => 'PT'], 'name' => 'Foreign buyer'], true],
]);
