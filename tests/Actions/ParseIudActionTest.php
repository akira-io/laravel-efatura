<?php

declare(strict_types=1);

use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Data\IudData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Tests\Support\IdentifierFixtures as I;

it('reads every component of the node-efatura reference identifier', function (): void {
    $iud = resolve(ParseIudAction::class)->handle(I::NODE_IUD);

    expect($iud)->toBeInstanceOf(IudData::class)
        ->and($iud->repository)->toBe(Environment::Test)
        ->and($iud->issueDate->format('Y-m-d'))->toBe('2026-02-08')
        ->and($iud->emitterTaxId)->toBe('100200300')
        ->and($iud->ledCode)->toBe(123)
        ->and($iud->documentType)->toBe(DocumentType::Invoice)
        ->and($iud->documentNumber)->toBe(1)
        ->and($iud->randomCode)->toBe('1234567890');
});

it('rejects identifiers that are not official IUDs', function (string $iud): void {
    expect(fn (): IudData => resolve(ParseIudAction::class)->handle($iud))
        ->toFailValidationOn('iud', 'The iud must be an official IUD with a valid check digit.');
})->with(fn (): array => [
    'forty four characters'     => [substr(I::NODE_IUD, 0, 44)],
    'forty six characters'      => [I::NODE_IUD . '0'],
    'letter in the middle'      => [substr_replace(I::NODE_IUD, 'A', 20, 1)],
    'lower case country'        => ['cv' . substr(I::NODE_IUD, 2)],
    'month thirteen'            => [I::withCheckDigit('CV326130810020030000123010000000011234567890')],
    'day thirty two'            => [I::withCheckDigit('CV326023210020030000123010000000011234567890')],
    'swapped check digit'       => [substr(I::NODE_IUD, 0, 44) . '8'],
    'altered document number'   => [substr_replace(I::NODE_IUD, '1', 30, 1)],
    'date before 2021'          => [I::withCheckDigit('CV320123110020030000123010000000011234567890')],
    'thirtieth of February'     => [I::withCheckDigit('CV326023010020030000123010000000011234567890')],
    'repository zero'           => [I::withCheckDigit('CV026020810020030000123010000000011234567890')],
    'repository four'           => [I::withCheckDigit('CV426020810020030000123010000000011234567890')],
    'LED zero'                  => [I::withCheckDigit('CV326020810020030000000010000000011234567890')],
    'document type zero'        => [I::withCheckDigit('CV326020810020030000123000000000011234567890')],
    'document type ten'         => [I::withCheckDigit('CV326020810020030000123100000000011234567890')],
    'document number zero'      => [I::withCheckDigit('CV326020810020030000123010000000001234567890')],
    'tax id starting with zero' => [I::withCheckDigit('CV326020801002003000123010000000011234567890')],
]);

it('reports a failure at the field the caller names', function (): void {
    expect(fn (): IudData => resolve(ParseIudAction::class)->handle('bad', 'references.0.fiscalDocument.value'))
        ->toFailValidationOn('references.0.fiscalDocument.value', 'The references.0.fiscalDocument.value must be an official IUD with a valid check digit.');
});

it('reads the year of the identifier in the twenty-first century', function (): void {
    $iud = resolve(ParseIudAction::class)->handle(I::withCheckDigit('CV399123110020030000123010000000011234567890'));

    expect($iud->issueDate->format('Y-m-d'))->toBe('2099-12-31');
});
