<?php

declare(strict_types=1);
use Akira\Efatura\Data\CreditNoteData;
use Akira\Efatura\Data\DebitNoteData;
use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\ReturnNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Data\TransportDocumentData;
use Akira\Efatura\Enums\IssueReason;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\LaravelDataServiceProvider;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
});
afterEach(fn () => CarbonImmutable::setTestNow());

it('validates the complete reason compatibility matrix', function (string $class, array $allowed): void {
    foreach (IssueReason::cases() as $reason) {
        $payload = F::payload(['issueReasonCode' => $reason->value, 'references' => F::references()]);
        if ($class === ReturnNoteData::class) {
            $payload['issueReasonDescription'] = 'Goods returned by buyer';
        }

        if (in_array($reason->value, $allowed, true)) {
            expect($class::from($payload)->issueReasonCode)->toBe($reason);
        } else {
            try {
                $class::from($payload);
                test()->fail('Incompatible reason accepted');
            } catch (ValidationException $exception) {
                expect($exception->errors())->toHaveKey('issueReasonCode');
            }
        }
    }
})->with([[CreditNoteData::class, ['2', '3', '6', '7', '8', '9', 'IN', 'DRP']], [DebitNoteData::class, ['2', '3', '4', '6', '8', '9', 'IN', 'DD']], [ReturnNoteData::class, ['0', '2', '3', '6', '7', '8', '9', 'IN']]]);

it('rejects explicit empty constrained document text', function (string $class, array $changes, string $field): void {
    try {
        $class::from(F::payload($changes));
        test()->fail('Empty constrained text accepted');
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey($field);
    }
})->with([
    [ElectronicInvoiceData::class, ['orderReference' => ''], 'orderReference'],
    [ReturnNoteData::class, ['issueReasonCode' => '2', 'issueReasonDescription' => ' ', 'references' => F::references()], 'issueReasonDescription'],
]);

it('keeps fiscal header values explicit and validates supplied allocations', function (): void {
    $header = DocumentHeaderData::from(['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 99999, 'serie' => 'A-1', 'documentNumber' => 999999999, 'innerDocumentNumber' => 'INV-1', 'isIsolatedAct' => true]);
    expect($header->documentNumber)->toBe(999999999)->and($header->serie)->toBe('A-1');
    foreach (['ledCode' => 0, 'serie' => 'bad space', 'documentNumber' => 0, 'innerDocumentNumber' => ''] as $field => $value) {
        expect(fn (): DocumentHeaderData => DocumentHeaderData::from(array_replace($header->toArray(), [$field => $value])))->toThrow(ValidationException::class);
    }
});

it('validates footer extensions as typed text and preserves empty extension content', function (): void {
    $footer = DocumentFooterData::validateAndCreate(['note' => 'Customer delivery note', 'extraFields' => [['name' => 'CustomerHint', 'value' => '']]]);
    expect($footer->extraFields[0]->value)->toBe('');
    expect(fn (): DocumentFooterData => DocumentFooterData::from(['note' => 'short']))->toThrow(ValidationException::class);
});

it('uses an injected clock and inclusive seven day contingency floor', function (): void {
    CarbonImmutable::setTestNow('2026-10-09T12:00:00-01:00');
    $payload = F::payload(['emission' => ['issueMode' => 2, 'contingency' => ['issueDate' => '2026-10-02', 'issueTime' => '12:00:00', 'ledCode' => 1, 'reasonTypeCode' => '4']]]);
    expect(ElectronicInvoiceData::from($payload)->header->issueDate->format('Y-m-d'))->toBe('2026-10-02');
    $payload['header']['issueTime'] = '11:59:59';
    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))->toThrow(ValidationException::class);
});

it('rejects future tax point and mismatched immediate payment date', function (): void {
    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from(F::payload(['taxPointDate' => '2026-10-03'])))->toThrow(ValidationException::class);
    expect(fn (): ReceiptInvoiceData => ReceiptInvoiceData::from(F::payload(['payments' => ['payments' => [['paymentDate' => '2026-10-01']]]])))->toThrow(ValidationException::class);
    expect(ElectronicInvoiceData::from(F::payload(['taxPointDate' => '2026-10-01', 'dueDate' => '2026-10-31', 'orderReference' => 'ORDER-1']))->dueDate->format('Y-m-d'))->toBe('2026-10-31');
});

it('validates supplied transmission identities without inventing software', function (): void {
    $context = EmissionContextData::validateAndCreate(['transmitterTaxId' => ['value' => '123456789', 'countryCode' => 'CV'], 'software' => ['code' => 'APP', 'name' => 'Fiscal App', 'version' => '1.0']]);
    expect($context->software->code)->toBe('APP');
    expect(fn (): EmissionContextData => EmissionContextData::from(['transmitterTaxId' => ['value' => '123', 'countryCode' => 'PT']]))->toThrow(ValidationException::class);
});

it('requires a real buyer when self billing a sales receipt', function (): void {
    $payload                          = F::payload(['receiver' => null, 'payments' => F::payments()]);
    $payload['header']['selfBilling'] = ['authorizationId' => '12345678-1234-1234-1234-123456789abc', 'authorizationCode' => '1234'];
    expect(fn (): SalesReceiptData => SalesReceiptData::from($payload))->toThrow(ValidationException::class);
});

it('resolves transport provider references only to existing parties', function (): void {
    $payload = F::payload(['transportDocumentTypeCode' => '2', 'transportServiceProvider' => ['reference' => 'RP'], 'transportRoute' => F::route()]);
    unset($payload['totals']);
    expect(TransportDocumentData::from($payload)->transportServiceProvider->reference->value)->toBe('RP');
    $payload['receiver']         = null;
    $payload['receiverTypeCode'] = '3';
    expect(fn (): TransportDocumentData => TransportDocumentData::from($payload))->toThrow(ValidationException::class);
});

it('requires charge references to target normal lines', function (): void {
    $payload = F::payload(['lines' => [F::linePayload(['id' => 'A', 'lineTypeCode' => 'I']), F::linePayload(['lineTypeCode' => 'C', 'lineReferenceId' => 'A'])]]);
    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))->toThrow(ValidationException::class);
});

it('recognizes zero as a valid line id and detects its duplicates', function (): void {
    $payload = F::payload(['lines' => [F::linePayload(['id' => '0']), F::linePayload(['lineTypeCode' => 'C', 'lineReferenceId' => '0'])],
        'totals'                   => F::totalsPayload(['priceExtensionTotalAmount' => '200', 'netTotalAmount' => '200', 'taxTotalAmount' => '30', 'payableAmount' => '230'])]);
    $document = ElectronicInvoiceData::from($payload);
    expect($document->lines[1]->lineReferenceId)->toBe('0');
    $payload['lines'][1] = F::linePayload(['id' => '0']);
    expect(fn (): ElectronicInvoiceData => ElectronicInvoiceData::from($payload))->toThrow(ValidationException::class);
});
