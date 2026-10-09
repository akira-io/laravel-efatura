<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Rules\UnreservedFiscalField;
use Akira\Efatura\Rules\ValidTaxId;
use Akira\Efatura\Support\Catalogs;
use Akira\Efatura\Tests\Support\DocumentPayloads as P;
use Illuminate\Support\Facades\Validator;

it('accepts a known official code', function (): void {
    expect(Validator::make(['code' => 'CV'], ['code' => [new OfficialCode(Catalog::Countries, resolve(Catalogs::class))]])->errors()->all())->toBe([]);
});

it('rejects unknown and incorrectly cased official codes', function (string|int $code): void {
    $validator = Validator::make(['code' => $code], ['code' => [new OfficialCode(Catalog::Countries, resolve(Catalogs::class))]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->get('code'))->toBe(['The code must be a code in the official catalog.']);
})->with([
    'lower case' => ['cv'],
    'integer'    => [1],
]);

it('rejects unsupported fiscal number input types', function (float|string $value): void {
    $validator = Validator::make(['value' => $value], ['value' => [FiscalNumber::nonNegative()]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->get('value'))->toBe(['Value must be a plain decimal number.']);
})->with([
    'float'               => [1.2],
    'scientific notation' => ['1e3'],
]);

it('rejects a tax identifier that is not text', function (): void {
    $validator = Validator::make(['value' => 12], ['value' => [new ValidTaxId('CV')]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->get('value'))->toBe(['The value must be a valid tax identifier for its country.']);
});

it('accepts tax identifiers valid for their declared country', function (string $taxId, string $country): void {
    expect(Validator::make(['taxId' => $taxId], ['taxId' => [new ValidTaxId($country)]])->errors()->all())->toBe([]);
})->with([
    'Cabo Verde' => ['123456789', 'CV'],
    'Portugal'   => ['AB12345', 'PT'],
]);

it('rejects a tax identifier invalid for its declared country', function (): void {
    $validator = Validator::make(['taxId' => '012345678'], ['taxId' => [new ValidTaxId('CV')]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->get('taxId'))->toBe(['The tax id must be a valid tax identifier for its country.']);
});

it('resolves package rule errors through the published translation namespace', function (): void {
    $validator = Validator::make(['taxId' => 'invalid'], ['taxId' => [new ValidTaxId('CV')]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('taxId'))->toBe('The tax id must be a valid tax identifier for its country.');
});

it('rejects a non-string extra field name with its own message', function (mixed $name, string $message): void {
    $validator = Validator::make(['name' => $name], ['name' => [new UnreservedFiscalField]]);

    expect($validator->errors()->get('name'))->toBe([$message]);
})->with([
    'integer'  => [12, 'The name must be a text field name.'],
    'array'    => [['IssueDate'], 'The name must be a text field name.'],
    'reserved' => ['IssueDate', 'The name is reserved for an official fiscal field.'],
]);

it('accepts series of up to twenty characters joined by single inner separators', function (string $series): void {
    expect(DocumentHeaderData::from(P::allocatedHeader(['serie' => $series]))->series)->toBe($series);
})->with([
    'twenty characters' => [str_repeat('A', 20)],
    'underscore'        => ['A_B'],
    'hyphen'            => ['A-B'],
]);

it('rejects series beyond the length or separator pattern', function (string $series, string $message): void {
    expect(fn (): DocumentHeaderData => DocumentHeaderData::from(P::allocatedHeader(['serie' => $series])))->toFailValidationOn('serie', $message);
})->with([
    'twenty one characters' => [str_repeat('A', 21), 'The serie field must not be greater than 20 characters.'],
    'dot'                   => ['A.B', 'The serie field format is invalid.'],
    'double separator'      => ['A__B', 'The serie field format is invalid.'],
    'leading separator'     => ['-A', 'The serie field format is invalid.'],
    'trailing separator'    => ['A-', 'The serie field format is invalid.'],
]);
