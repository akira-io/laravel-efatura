<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Tests\Support\DocumentXmlGraphs;
use Akira\Efatura\Tests\Support\EventFixtures as E;
use Akira\Efatura\Tests\Support\IdentifierFixtures;
use Akira\Efatura\Tests\Support\SchemaFixtures as S;
use Akira\Efatura\Tests\Support\XmlFixtures as X;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(DocumentXmlGraphs::NOW);
});

it('validates the minimal and maximal graph of every document type', function (DocumentType $type, string $graph): void {
    $xml = X::documentXml($graph === 'minimal' ? X::minimal($type) : X::maximal($type));

    resolve(SchemaValidator::class)->validate($xml);

    expect($xml)->toContain('DocumentTypeCode="' . $type->code() . '"');
})->with(DocumentType::cases())->with(['minimal', 'maximal']);

it('validates an invoice in every emission mode', function (array $emission, string $issueMode): void {
    $xml = X::documentXml(X::maximal(DocumentType::Invoice, $emission));

    resolve(SchemaValidator::class)->validate($xml);

    expect($xml)->toContain('<IssueMode>' . $issueMode . '</IssueMode>');
})->with([
    'online'  => [[], '1'],
    'offline' => [X::offline(), '2'],
    'off'     => [X::off(), '3'],
]);

it('validates a specimen invoice in every repository', function (Environment $repository): void {
    $xml = X::documentXml(X::maximal(DocumentType::Invoice), $repository, isSpecimen: true);

    resolve(SchemaValidator::class)->validate($xml);

    expect($xml)->toContain('<IsSpecimen>true</IsSpecimen>')
        ->and($xml)->toContain('<RepositoryCode>' . $repository->value . '</RepositoryCode>');
})->with(Environment::cases());

it('validates cancellation and unused number events', function (array $payload): void {
    $xml = X::eventXml($payload);

    expect(fn () => resolve(SchemaValidator::class)->validate($xml))->not->toThrow(SchemaValidationException::class);
})->with([
    'one iud'            => [['iuds' => [E::iud()]]],
    'several iuds'       => [['iuds' => [E::iud(), IdentifierFixtures::NODE_IUD]]],
    'range with year'    => [['eventTypeCode' => 'UDN', 'numberRange' => [...E::numberRange(1, 10), 'year' => 2026]]],
    'range without year' => [['eventTypeCode' => 'UDN', 'numberRange' => E::numberRange(1, 10)]],
]);

it('validates the official examples against their signature profile', function (string $example, SignatureProfile $profile): void {
    expect(fn () => resolve(SchemaValidator::class)->validate(S::official($example), $profile))->not->toThrow(SchemaValidationException::class);
})->with([
    ['1 Invoice - EnvelopedSignature.xml', SignatureProfile::Enveloped],
    ['1 Invoice - InternallyDetachedSignature.xml', SignatureProfile::InternallyDetached],
    ['2 InvoiceReceipt.xml', SignatureProfile::Enveloped],
    ['3 SalesReceipt.xml', SignatureProfile::Enveloped],
    ['4 Receipt.xml', SignatureProfile::Enveloped],
    ['6 DebitNote.xml', SignatureProfile::Enveloped],
    ['7 Transport.xml', SignatureProfile::Enveloped],
    ['9 RegistrationNote.xml', SignatureProfile::Enveloped],
    ['99 Event.xml', SignatureProfile::Enveloped],
]);

it('validates the official examples with a 46 digit signature serial number once the signature is removed', function (string $example): void {
    expect(fn () => resolve(SchemaValidator::class)->validate(S::withoutSignature(S::official($example))))->not->toThrow(SchemaValidationException::class);
})->with(['5 CreditNote.xml', '8 ReturnNote.xml']);

it('fails those official examples at most on the signature serial number, depending on the libxml version', function (string $example): void {
    try {
        resolve(SchemaValidator::class)->validate(S::official($example));
        $messages = [];
    } catch (SchemaValidationException $schemaValidationException) {
        $messages = array_column($schemaValidationException->violations, 'message');
    }

    expect($messages)->toBeIn([[], ["Element \x27{http://www.w3.org/2000/09/xmldsig#}X509SerialNumber\x27: \x27…\x27"]]);
})->with(['5 CreditNote.xml', '8 ReturnNote.xml']);
