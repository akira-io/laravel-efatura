<?php

declare(strict_types=1);

use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\ReferenceData;
use Akira\Efatura\Rules\IudCheckDigit;
use Akira\Efatura\Tests\Support\EventFixtures as E;
use Akira\Efatura\Tests\Support\IdentifierFixtures as I;
use Illuminate\Support\Facades\Validator;

it('accepts a received identifier with its check digit', function (): void {
    $event = EventData::from(E::payload(['iuds' => [E::iud(), I::NODE_IUD]]));

    expect($event->iuds)->toBe([E::iud(), I::NODE_IUD]);
});

it('rejects a cancelled identifier with a wrong check digit', function (): void {
    $payload = E::payload(['iuds' => [substr(E::iud(), 0, 44) . '4']]);

    expect(fn (): EventData => EventData::from($payload))->toFailValidationOn('iuds.0', 'The iuds.0 must be an official IUD with a valid check digit.');
});

it('rejects a referenced identifier with a wrong check digit', function (): void {
    $payload = ['fiscalDocument' => ['value' => substr(I::NODE_IUD, 0, 44) . '0']];

    expect(fn (): ReferenceData => ReferenceData::from($payload))
        ->toFailValidationOn('fiscalDocument.value', 'The fiscal document.value must be an official IUD with a valid check digit.');
});

it('keeps accepting an old document reference', function (): void {
    $reference = ReferenceData::from(['fiscalDocument' => ['value' => '1/2021/A/1', 'isOldDocument' => true]]);

    expect($reference->fiscalDocument?->value)->toBe('1/2021/A/1');
});

it('leaves values that are not official IUDs to the pattern rule', function (mixed $value): void {
    expect(Validator::make(['iud' => $value], ['iud' => [new IudCheckDigit]])->errors()->all())->toBe([]);
})->with([
    'old reference' => ['1/2021/A/1'],
    'malformed'     => ['CV12'],
    'not text'      => [12],
]);
