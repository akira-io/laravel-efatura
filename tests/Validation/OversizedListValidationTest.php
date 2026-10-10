<?php

declare(strict_types=1);

use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Akira\Efatura\Tests\Support\EventFixtures as E;
use Akira\Efatura\Tests\Support\LimitFixtures;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

it('rejects an oversized nested list under its full path', function (array $payload, string $field, string $message): void {
    expect(fn (): DocumentData => ElectronicInvoiceData::from($payload))->toFailValidationOn($field, $message);
})->with([
    'payee financial accounts' => [
        F::payload(['payments' => ['payeeFinancialAccounts' => array_fill(0, 101, ['name' => 'Bank Account', 'nib' => '123456789012345678901'])]]),
        'payments.payeeFinancialAccounts',
        'The payments.payee financial accounts field must not have more than 100 items.',
    ],
    'payments' => [F::payload(['payments' => ['payments' => array_fill(0, 101, ['paymentMeansCode' => '10'])]]),
        'payments.payments', 'The payments.payments field must not have more than 100 items.'],
    'item extra properties' => [
        F::payload(['lines' => [F::linePayload(['item' => LimitFixtures::itemWithExtraProperties(101)])]]),
        'lines.0.item.extraProperties',
        'The lines.0.item.extra properties field must not have more than 100 items.',
    ],
    'line taxes' => [F::payload(['lines' => [F::linePayload(['taxes' => array_fill(0, 3, ['taxTypeCode' => 'IVA', 'taxPercentage' => '15'])])]]),
        'lines.0.taxes', 'The lines.0.taxes field must not have more than 2 items.'],
    'alternative amounts' => [F::payload(['totals' => F::totalsPayload(['payableAlternativeAmounts' => LimitFixtures::alternativeAmounts(101)])]),
        'totals.payableAlternativeAmounts', 'The totals.payable alternative amounts field must not have more than 100 items.'],
    'footer extra fields' => [F::payload(['footer' => ['extraFields' => array_fill(0, 101, ['name' => 'CustomerTag', 'value' => 'v'])]]),
        'footer.extraFields', 'The footer.extra fields field must not have more than 100 items.'],
]);

it('rejects an oversized list before building a rule for its items', function (Closure $create, string $field, string $message): void {
    $ruleSets = [];
    resolve(Factory::class)->resolver(function (Translator $translator, array $data, array $rules, array ...$labels) use (&$ruleSets): Validator {
        $ruleSets[] = array_keys($rules);

        return new Validator($translator, $data, $rules, ...$labels);
    });

    try {
        $create();
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toBe([$field => [$message]])
            ->and($ruleSets)->toBe([[$field]]);

        return;
    }

    $this->fail('Validation passed, expected an oversized list to fail.');
})->with([
    'lines' => [
        fn (): DocumentData => ElectronicInvoiceData::from(F::payload(['lines' => array_fill(0, 20000, F::linePayload(['quantity' => []]))])),
        'lines',
        'The lines field must not have more than 1000 items.',
    ],
    'references' => [
        fn (): DocumentData => ElectronicInvoiceData::from(F::payload(['references' => array_fill(0, 20000, F::references()[0])])),
        'references',
        'The references field must not have more than 1000 items.',
    ],
    'iuds' => [fn (): EventData => EventData::from(E::payload(['iuds' => LimitFixtures::iuds(20000)])),
        'iuds', 'The iuds field must not have more than 1000 items.'],
]);
