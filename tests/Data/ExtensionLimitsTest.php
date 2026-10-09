<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentFooterData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Data\ExtraPropertyData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\PaymentsData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\EventFixtures as E;
use Akira\Efatura\Tests\Support\LimitFixtures;

it('bounds the text of extensions', function (string $class, array $payload, string $field, string $message): void {
    expect(fn (): mixed => $class::from($payload))->toFailValidationOn($field, $message);
})->with([
    'extra field name'      => [ExtraFieldData::class, ['name' => str_repeat('n', 51), 'value' => 'v'], 'name', 'The name field must not be greater than 50 characters.'],
    'extra field value'     => [ExtraFieldData::class, ['name' => 'CustomerTag', 'value' => str_repeat('v', 1001)], 'value', 'The value field must not be greater than 1000 characters.'],
    'extra field namespace' => [ExtraFieldData::class, ['name' => 'CustomerTag', 'value' => 'v', 'namespace' => 'urn:' . str_repeat('x', 253)], 'namespace',
        'The namespace field must not be greater than 256 characters.'],
    'extra property value' => [ExtraPropertyData::class, ['name' => 'Colour', 'value' => str_repeat('v', 1001)], 'value', 'The value field must not be greater than 1000 characters.'],
]);

it('accepts extension text at its limits', function (): void {
    $field    = ExtraFieldData::from(['name' => str_repeat('n', 50), 'value' => str_repeat('v', 1000), 'namespace' => 'urn:' . str_repeat('x', 252)]);
    $property = ExtraPropertyData::from(['name' => 'Colour', 'value' => str_repeat('v', 1000)]);

    expect(mb_strlen($field->value))->toBe(1000)
        ->and(mb_strlen((string) $field->namespace))->toBe(256)
        ->and(mb_strlen($property->value))->toBe(1000);
});

it('bounds the number of entries in extension and payment lists', function (string $class, array $payload, string $field, string $label): void {
    expect(fn (): mixed => $class::from($payload))->toFailValidationOn($field, "The {$label} field must not have more than 100 items.");
})->with([
    'extra fields'     => [DocumentFooterData::class, ['extraFields' => array_fill(0, 101, ['name' => 'CustomerTag', 'value' => 'v'])], 'extraFields', 'extra fields'],
    'extra properties' => [ItemData::class, ['description' => 'Item', 'emitterIdentification' => 'SKU', 'extraProperties' => array_fill(0, 101, ['name' => 'Colour', 'value' => 'Blue'])],
        'extraProperties', 'extra properties'],
    'payments'                 => [PaymentsData::class, ['payments' => array_fill(0, 101, ['paymentMeansCode' => '10'])], 'payments', 'payments'],
    'payee financial accounts' => [PaymentsData::class, ['payeeFinancialAccounts' => array_fill(0, 101, ['name' => 'Bank Account', 'nib' => '123456789012345678901'])],
        'payeeFinancialAccounts', 'payee financial accounts'],
]);

it('accepts a hundred entries in each bounded list', function (): void {
    expect(DocumentFooterData::from(['extraFields' => array_fill(0, 100, ['name' => 'CustomerTag', 'value' => 'v'])])->extraFields)->toHaveCount(100)
        ->and(PaymentsData::from(['payments' => array_fill(0, 100, ['paymentMeansCode' => '10'])])->payments)->toHaveCount(100);
});

it('bounds event targets and alternative payable amounts', function (string $class, array $payload, string $field, string $message): void {
    expect(fn (): mixed => $class::from($payload))->toFailValidationOn($field, $message);
})->with([
    'event iuds'          => [EventData::class, E::payload(['iuds' => LimitFixtures::iuds(1001)]), 'iuds', 'The iuds field must not have more than 1000 items.'],
    'alternative amounts' => [TotalsData::class, F::totalsPayload(['payableAlternativeAmounts' => LimitFixtures::alternativeAmounts(101)]),
        'payableAlternativeAmounts', 'The payable alternative amounts field must not have more than 100 items.'],
]);

it('accepts event targets and alternative payable amounts at their limits', function (): void {
    expect(EventData::from(E::payload(['iuds' => LimitFixtures::iuds(1000)]))->iuds)->toHaveCount(1000)
        ->and(TotalsData::from(F::totalsPayload(['payableAlternativeAmounts' => LimitFixtures::alternativeAmounts(100)]))->payableAlternativeAmounts)->toHaveCount(100);
});
