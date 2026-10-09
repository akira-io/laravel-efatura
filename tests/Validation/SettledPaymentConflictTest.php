<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\PaymentData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\PaymentTermsData;
use Akira\Efatura\Data\ReceiptData;
use Akira\Efatura\Data\ReceiptInvoiceData;
use Akira\Efatura\Data\RegistrationNoteData;
use Akira\Efatura\Data\SalesReceiptData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('reports settled payments on an invoice once, as prohibited', function (): void {
    $payload = F::payload(['payments' => ['paymentDueDate' => '2026-10-31', ...F::payments()]]);

    expect(fn (): DocumentData => ElectronicInvoiceData::from($payload))->toThrow(function (ValidationException $exception): void {
        expect(Arr::where($exception->errors(), fn (array $messages, string $field): bool => Str::startsWith($field, 'payments')))
            ->toBe(['payments.payments' => ['The payments.payments field is prohibited.']]);
    });
});

it('reports a due date beside settled payments once, at the due date', function (string $class, array $payload): void {
    $payload['payments'] = ['paymentDueDate' => '2026-10-31', ...F::payments()];

    expect(fn (): DocumentData => $class::from($payload))->toThrow(function (ValidationException $exception): void {
        expect(Arr::where($exception->errors(), fn (array $messages, string $field): bool => Str::startsWith($field, 'payments')))
            ->toBe(['payments.paymentDueDate' => ['The payments.payment due date field is prohibited.']]);
    });
})->with([
    'FRE' => [ReceiptInvoiceData::class, F::payload()],
    'TVE' => [SalesReceiptData::class, F::payload()],
    'NLE' => [RegistrationNoteData::class, F::payload()],
    'RCE' => [ReceiptData::class, F::receiptPayload('1')],
]);

it('leaves the choice between terms and settled payments to the document', function (): void {
    $payments = PaymentsData::from(['paymentTerms' => new PaymentTermsData('Payment within thirty days'), 'payments' => [new PaymentData]]);

    expect($payments->paymentTerms?->note)->toBe('Payment within thirty days')
        ->and($payments->payments)->toHaveCount(1);
});
