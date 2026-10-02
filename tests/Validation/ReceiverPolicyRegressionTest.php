<?php

declare(strict_types=1);

use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('accepts an omitted return receiver while retaining supplied parties and self billing requirements', function (string $factory, bool $receiver, bool $selfBilling): void {
    $payload = F::payload(['issueReasonCode' => '2', 'references' => F::references()]);
    if (! $receiver) {
        unset($payload['receiver']);
    }

    if ($selfBilling) {
        $payload['header']['selfBilling'] = ['authorizationId' => '12345678-1234-1234-1234-123456789abc', 'authorizationCode' => '1234'];
    }

    $create = fn (): ReturnNoteData => ReturnNoteData::$factory($payload);
    if ($selfBilling && ! $receiver) {
        expect($create)->toThrow(ValidationException::class);
    } else {
        expect($create()->receiver?->taxId?->countryCode)->toBe($receiver ? 'CV' : null);
    }
})->with(['from', 'validateAndCreate'])->with([[false, false], [true, false], [false, true], [true, true]]);

it('requires CV identity only for transport taxpayer receivers and resolves emitter references', function (string $factory, string $type, array $receiver, bool $valid): void {
    $payload = F::payload(['receiver' => $receiver, 'receiverTypeCode' => $type, 'transportDocumentTypeCode' => '2',
        'transportServiceProvider'    => ['reference' => 'EP'], 'transportRoute' => F::route()]);
    unset($payload['totals']);
    $create = fn (): TransportDocumentData => TransportDocumentData::$factory($payload);
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
})->with(['from', 'validateAndCreate'])->with([
    ['1', ['taxId' => ['value' => '123456789', 'countryCode' => 'PT'], 'name' => 'Foreign buyer'], false],
    ['1', ['taxId' => ['value' => '123456789', 'countryCode' => 'CV'], 'name' => 'Local buyer'], true],
    ['1', ['reference' => 'EP'], true],
    ['2', ['taxId' => ['value' => '123456789', 'countryCode' => 'PT'], 'name' => 'Foreign buyer'], true],
]);
