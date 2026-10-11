<?php

declare(strict_types=1);

use Akira\Efatura\Data\SelfBillingAuthorizationRequestData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Tests\Support\SelfBillingFixtures as S;

it('carries the seller, the document type, the phone and the cve amount of a self-billing request', function (string $type, DocumentType $documentType): void {
    $request = S::request(['documentTypeCode' => $type]);

    expect($request->taxId)->toBe(S::SELLER)
        ->and($request->documentType)->toBe($documentType)
        ->and($request->mobilePhoneNumber)->toBe('9912345')
        ->and((string) $request->totalAmount->getAmount())->toBe('1500.50000')
        ->and($request->totalAmount->getCurrency()->getCurrencyCode())->toBe('CVE');
})->with([
    'invoice'         => ['FTE', DocumentType::Invoice],
    'invoice receipt' => ['FRE', DocumentType::InvoiceReceipt],
    'receipt'         => ['RCE', DocumentType::Receipt],
    'credit note'     => ['NCE', DocumentType::CreditNote],
    'debit note'      => ['NDE', DocumentType::DebitNote],
    'return note'     => ['DVE', DocumentType::ReturnNote],
]);

it('refuses a request the platform cannot authorize', function (array $overrides, string $field, string $message): void {
    expect(fn (): SelfBillingAuthorizationRequestData => S::request($overrides))->toFailValidationOn($field, $message);
})->with([
    'a foreign tax id'       => [['taxId' => '12345'], 'taxId', 'The tax id field format is invalid.'],
    'a tax id starting at 0' => [['taxId' => '012345678'], 'taxId', 'The tax id field format is invalid.'],
    'a sales receipt'        => [['documentTypeCode' => 'TVE'], 'documentTypeCode', 'The document type code must be a document type that self-billing can authorize.'],
    'a transport document'   => [['documentTypeCode' => 'DTE'], 'documentTypeCode', 'The document type code must be a document type that self-billing can authorize.'],
    'a registration note'    => [['documentTypeCode' => 'NLE'], 'documentTypeCode', 'The document type code must be a document type that self-billing can authorize.'],
    'a short phone'          => [['mobilePhoneNumber' => '99123'], 'mobilePhoneNumber', 'The mobile phone number field format is invalid.'],
    'an excessive scale'     => [['totalAmount' => '10.123456'], 'totalAmount', 'Value exceeds the allowed decimal precision.'],
    'a negative amount'      => [['totalAmount' => '-10'], 'totalAmount', 'The total amount is outside its permitted numeric bounds.'],
    'a zero amount'          => [['totalAmount' => '0'], 'totalAmount', 'The total amount is outside its permitted numeric bounds.'],
]);
