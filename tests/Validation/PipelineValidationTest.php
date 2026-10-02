<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as LaravelValidator;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

it('validates a document payload once per entry point', function (string $method): void {
    $documentValidations = 0;
    Validator::resolver(function (Translator $translator, array $data, array $rules, array $messages, array $attributes) use (&$documentValidations): LaravelValidator {
        $documentValidations += array_key_exists('header.issueDate', $rules) ? 1 : 0;

        return new LaravelValidator($translator, $data, $rules, $messages, $attributes);
    });

    ElectronicInvoiceData::$method(F::payload());

    expect($documentValidations)->toBe(1);
})->with(['from', 'validateAndCreate']);

it('reports nested and document failures together at their full paths', function (): void {
    $lines                                   = array_fill(0, 3, F::linePayload());
    $lines[2]['taxes'][0]['taxTypeCode']     = 'XX';
    $payload                                 = F::payload(['lines' => $lines]);
    $payload['emitter']['contacts']['email'] = null;

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::validateAndCreate($payload))->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe([
            'emitter.contacts.email'      => ['The emitter.contacts.email field is required.'],
            'lines.2.taxes.0.taxTypeCode' => ['The selected lines.2.taxes.0.tax type code is invalid.'],
        ]);
    });
});

it('rejects fields that do not belong to the document type with the package message', function (): void {
    $payload = F::payload(['issueReasonCode' => '2']);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))->toThrow(function (ValidationException $exception): void {
        expect($exception->errors())->toBe(['issueReasonCode' => ['This field does not belong to this fiscal document type.']]);
    });
});

it('validates array payloads on from without opting in', function (): void {
    $payload = ['value' => '012345678', 'countryCode' => 'CV'];

    expect(fn (): TaxIdData => TaxIdData::from($payload))
        ->toFailValidationOn('value', 'The value must be a valid tax identifier for its country.');
});

it('constructs data objects directly without validating them', function (): void {
    expect(new TaxIdData('012345678', 'CV')->value)->toBe('012345678');
});

it('lists the contingency reasons allowed for each emission mode', function (EmissionMode $mode, array $reasons): void {
    expect(ContingencyReason::allowedFor($mode))->toBe($reasons);
})->with([
    'online'  => [EmissionMode::Online, []],
    'offline' => [EmissionMode::Offline, [
        ContingencyReason::Other, ContingencyReason::AuthorizationServiceUnavailable, ContingencyReason::InternetUnavailable, ContingencyReason::TimestampServiceUnavailable,
    ]],
    'off' => [EmissionMode::Off, [ContingencyReason::Other, ContingencyReason::PowerFailure, ContingencyReason::TaxpayerSystemUnavailable]],
]);

it('accepts a contingency reason allowed for the emission mode', function (EmissionMode $mode, ContingencyReason $reason): void {
    $document = ElectronicInvoiceData::from(F::payload(['emission' => ['issueMode' => $mode->value, 'contingency' => P::contingency($reason)]]));

    expect($document->emission?->contingency?->reason)->toBe($reason);
})->with([
    'internet outage offline' => [EmissionMode::Offline, ContingencyReason::InternetUnavailable],
    'power failure off'       => [EmissionMode::Off, ContingencyReason::PowerFailure],
]);

it('rejects a contingency reason not allowed for the emission mode', function (EmissionMode $mode, ContingencyReason $reason): void {
    $payload = F::payload(['emission' => ['issueMode' => $mode->value, 'contingency' => P::contingency($reason)]]);

    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))
        ->toFailValidationOn('emission.contingency.reasonTypeCode', 'The selected emission.contingency.reason type code is invalid.');
})->with([
    'power failure offline' => [EmissionMode::Offline, ContingencyReason::PowerFailure],
    'internet outage off'   => [EmissionMode::Off, ContingencyReason::InternetUnavailable],
]);
