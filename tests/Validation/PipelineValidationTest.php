<?php

declare(strict_types=1);

use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\TaxIdData;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as LaravelValidator;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function pipelineErrorsOf(Closure $creation): array
{
    try {
        $creation();
    } catch (ValidationException $validationException) {
        return $validationException->errors();
    }

    test()->fail('Expected a ValidationException.');
}

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

    $errors = pipelineErrorsOf(fn (): ElectronicInvoiceData => ElectronicInvoiceData::validateAndCreate($payload));

    expect($errors)->toHaveKeys(['lines.2.taxes.0.taxTypeCode', 'emitter.contacts.email']);
});

it('rejects fields that do not belong to the document type with the package message', function (): void {
    $errors = pipelineErrorsOf(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['issueReasonCode' => '2'])));

    expect($errors['issueReasonCode'] ?? null)->toBe(['This field does not belong to this fiscal document type.']);
});

it('validates array payloads on from without opting in', function (): void {
    expect(fn (): TaxIdData => TaxIdData::from(['value' => '012345678', 'countryCode' => 'CV']))
        ->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toHaveKey('value');
        });
});

it('constructs data objects directly without validating them', function (): void {
    expect(new TaxIdData('012345678', 'CV')->value)->toBe('012345678');
});

it('accepts only the contingency reasons allowed for the emission mode', function (EmissionMode $mode, ContingencyReason $reason, bool $allowed): void {
    $contingency = ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1, 'iuc' => '2026/1', 'reasonTypeCode' => $reason->value, 'reasonDescription' => 'Temporary service interruption'];
    $create      = fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['emission' => ['issueMode' => $mode->value, 'contingency' => $contingency]]));

    expect(in_array($reason, ContingencyReason::allowedFor($mode), true))->toBe($allowed);
    $allowed
        ? expect($create()->emission?->contingency?->reasonTypeCode)->toBe($reason)
        : expect($create)->toThrow(function (ValidationException $exception): void {
            expect($exception->errors())->toHaveKey('emission.contingency.reasonTypeCode');
        });
})->with([
    [EmissionMode::Offline, ContingencyReason::InternetUnavailable, true],
    [EmissionMode::Offline, ContingencyReason::PowerFailure, false],
    [EmissionMode::Off, ContingencyReason::PowerFailure, true],
    [EmissionMode::Off, ContingencyReason::InternetUnavailable, false],
]);
