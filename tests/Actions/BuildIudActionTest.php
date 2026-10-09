<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildIudAction;
use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\IudData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Support\Luhn;
use Akira\Efatura\Tests\Support\DocumentFixtures;
use Akira\Efatura\Tests\Support\DocumentPayloads;
use Akira\Efatura\Tests\Support\IdentifierFixtures as I;
use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Random\Randomizer;

it('builds the node-efatura reference identifier', function (): void {
    expect(resolve(BuildIudAction::class)->handle(IudData::from(I::iudPayload())))->toBe(I::NODE_IUD);
});

it('builds an identifier the parser reads back for every document type and repository', function (DocumentType $type, Environment $repository): void {
    $iud = resolve(BuildIudAction::class)->handle(IudData::from(I::iudPayload(['documentTypeCode' => $type->value, 'repositoryCode' => $repository->value])));

    $parsed = resolve(ParseIudAction::class)->handle($iud);

    expect($iud)->toHaveLength(45)
        ->and(substr($iud, 2, 1))->toBe((string) $repository->value)
        ->and(substr($iud, 23, 2))->toBe(sprintf('%02d', $type->code()))
        ->and($parsed->documentType)->toBe($type)
        ->and($parsed->repository)->toBe($repository)
        ->and($parsed->toPayload())->toBe(IudData::from(I::iudPayload(['documentTypeCode' => $type->value, 'repositoryCode' => $repository->value]))->toPayload());
})->with(fn (): array => DocumentType::cases())->with(fn (): array => Environment::cases());

it('pads every numeric component to its fixed width', function (array $overrides, int $offset, string $segment): void {
    $iud = resolve(BuildIudAction::class)->handle(IudData::from(I::iudPayload($overrides)));

    expect(substr($iud, $offset, strlen($segment)))->toBe($segment)
        ->and(resolve(ParseIudAction::class)->handle($iud)->toPayload())->toBe(IudData::from(I::iudPayload($overrides))->toPayload());
})->with([
    'smallest LED'             => [['ledCode' => 1], 18, '00001'],
    'largest LED'              => [['ledCode' => 99999], 18, '99999'],
    'smallest document number' => [['documentNumber' => 1], 25, '000000001'],
    'largest document number'  => [['documentNumber' => 999999999], 25, '999999999'],
    'smallest random code'     => [['randomCode' => '0000000000'], 34, '0000000000'],
    'largest random code'      => [['randomCode' => '9999999999'], 34, '9999999999'],
    'issue date'               => [['issueDate' => '2099-12-31'], 3, '991231'],
]);

it('dates the identifier on the Cape Verde day of the issue instant', function (): void {
    $moment = new CarbonImmutable('2026-10-03T00:30:00Z');

    $built = resolve(BuildIudAction::class)->handle(IudData::from(I::iudPayload(['issueDate' => $moment])));
    $new   = resolve(BuildIudAction::class)->handle(new IudData(Environment::Test, $moment, '100200300', 1, DocumentType::Invoice, 1, '1234567890'));

    expect(substr($built, 3, 6))->toBe('261002')
        ->and(substr($new, 3, 6))->toBe('261002')
        ->and(IudData::from(I::iudPayload(['issueDate' => $moment]))->issueDate->format('Y-m-d'))->toBe('2026-10-02');
});

it('generates the random code from the injected randomizer', function (): void {
    $payload = IudData::from(I::iudPayload(['randomCode' => null]));

    $first  = new BuildIudAction(new Randomizer(new Mt19937(42)))->handle($payload);
    $second = new BuildIudAction(new Randomizer(new Mt19937(42)))->handle($payload);

    expect($first)->toBe($second)
        ->toHaveLength(45)
        ->toMatch('/\ACV[0-9]{43}\z/')
        ->and(Luhn::passes(substr($first, 2)))->toBeTrue()
        ->and(resolve(ParseIudAction::class)->handle($first)->randomCode)->toBe(substr($first, 34, 10));
});

it('binds a cryptographically secure randomizer', function (): void {
    expect(resolve(Randomizer::class)->engine)->toBeInstanceOf(Secure::class);
});

it('rejects identifier components outside their official bounds', function (array $overrides, string $field, string $message): void {
    expect(fn (): IudData => IudData::from(I::iudPayload($overrides)))->toFailValidationOn($field, $message);
})->with([
    'tax id with eight digits'      => [['emitterTaxId' => '10020030'], 'emitterTaxId', 'The emitter tax id field format is invalid.'],
    'tax id with ten digits'        => [['emitterTaxId' => '1002003000'], 'emitterTaxId', 'The emitter tax id field format is invalid.'],
    'tax id starting with zero'     => [['emitterTaxId' => '010020030'], 'emitterTaxId', 'The emitter tax id field format is invalid.'],
    'LED zero'                      => [['ledCode' => 0], 'ledCode', 'The led code field must be between 1 and 99999.'],
    'LED above five digits'         => [['ledCode' => 100000], 'ledCode', 'The led code field must be between 1 and 99999.'],
    'document number zero'          => [['documentNumber' => 0], 'documentNumber', 'The document number field must be between 1 and 999999999.'],
    'document number above nine'    => [['documentNumber' => 1000000000], 'documentNumber', 'The document number field must be between 1 and 999999999.'],
    'random code with nine digits'  => [['randomCode' => '123456789'], 'randomCode', 'The random code field format is invalid.'],
    'random code with letters'      => [['randomCode' => '12345abcde'], 'randomCode', 'The random code field format is invalid.'],
    'date before the fiscal epoch'  => [['issueDate' => '2020-12-31'], 'issueDate', 'The issue date must use a valid fiscal date or time.'],
    'date after the two digit year' => [['issueDate' => '2100-01-01'], 'issueDate', 'The issue date field must be a date before 2100-01-01.'],
    'unknown repository'            => [['repositoryCode' => 4], 'repositoryCode', 'The selected repository code is invalid.'],
]);

it('validates an identifier built with new before composing it', function (): void {
    $data = new IudData(Environment::Test, CarbonImmutable::parse('2026-10-02'), '010020030', 1, DocumentType::Invoice, 1);

    expect(fn (): string => resolve(BuildIudAction::class)->handle($data))->toFailValidationOn('emitterTaxId', 'The emitter tax id field format is invalid.');
});

it('takes the tax id of the emitter and never the transmitter', function (): void {
    $document = ElectronicInvoiceData::from(DocumentFixtures::payload(['emission' => DocumentPayloads::transmission()]));

    $iud = resolve(BuildIudAction::class)->handle(IudData::from(I::iudPayload(['emitterTaxId' => $document->emitter->taxId?->value])));

    expect(substr($iud, 9, 9))->toBe('100200300')
        ->and($document->emission?->transmitterTaxId?->value)->toBe('123456789')
        ->and(array_keys(IudData::from(I::iudPayload())->toPayload()))->not->toContain('transmitterTaxId');
});
