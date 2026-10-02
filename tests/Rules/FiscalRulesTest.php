<?php

declare(strict_types=1);

use Akira\Efatura\Enums\Catalog;
use Akira\Efatura\Rules\FiscalNumber;
use Akira\Efatura\Rules\OfficialCode;
use Akira\Efatura\Rules\ValidTaxId;
use Illuminate\Support\Facades\Validator;

it('rejects unknown and incorrectly cased official codes', function (): void {
    expect(Validator::make(['code' => 'CV'], ['code' => [new OfficialCode(Catalog::Countries)]])->passes())->toBeTrue()
        ->and(Validator::make(['code' => 'cv'], ['code' => [new OfficialCode(Catalog::Countries)]])->fails())->toBeTrue()
        ->and(Validator::make(['code' => 1], ['code' => [new OfficialCode(Catalog::Countries)]])->fails())->toBeTrue();
});

it('rejects unsupported rule input types', function (): void {
    expect(Validator::make(['value' => 1.2], ['value' => [FiscalNumber::nonNegative()]])->fails())->toBeTrue()
        ->and(Validator::make(['value' => '1e3'], ['value' => [FiscalNumber::nonNegative()]])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 12], ['value' => [new ValidTaxId('CV')]])->fails())->toBeTrue();
});

it('validates tax identifiers with their declared country', function (): void {
    expect(Validator::make(['taxId' => '123456789'], ['taxId' => [new ValidTaxId('CV')]])->passes())->toBeTrue()
        ->and(Validator::make(['taxId' => '012345678'], ['taxId' => [new ValidTaxId('CV')]])->fails())->toBeTrue()
        ->and(Validator::make(['taxId' => 'AB12345'], ['taxId' => [new ValidTaxId('PT')]])->passes())->toBeTrue();
});

it('resolves package rule errors through the published translation namespace', function (): void {
    $validator = Validator::make(['taxId' => 'invalid'], ['taxId' => [new ValidTaxId('CV')]]);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('taxId'))->toBe('The tax id must be a valid tax identifier for its country.');
});
