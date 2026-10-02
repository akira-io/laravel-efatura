<?php

declare(strict_types=1);

use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Rules\UnreservedFiscalField;
use Akira\Efatura\Rules\ValidTaxId;
use Akira\Efatura\Support\Catalogs;
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
