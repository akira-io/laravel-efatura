<?php

declare(strict_types=1);

use Akira\Efatura\Data\AddressData;
use Akira\Efatura\Data\ContactsData;
use Akira\Efatura\Data\ExtraFieldData;
use Akira\Efatura\Data\ItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\PayeeFinancialAccountData;
use Akira\Efatura\Data\PaymentData;
use Akira\Efatura\Data\StandardIdentificationData;
use Akira\Efatura\Enums\PartyReference;
use Spatie\LaravelData\Data;

dataset('optional string fields', [
    'contacts telephone'                 => [ContactsData::class, [], 'telephone', 'telephone'],
    'contacts mobilephone'               => [ContactsData::class, [], 'mobilephone', 'mobilephone'],
    'contacts telefax'                   => [ContactsData::class, [], 'telefax', 'telefax'],
    'contacts email'                     => [ContactsData::class, [], 'email', 'email'],
    'contacts website'                   => [ContactsData::class, [], 'website', 'website'],
    'standard identification ean'        => [StandardIdentificationData::class, ['gtin' => 'ABC'], 'ean', 'ean'],
    'standard identification upc'        => [StandardIdentificationData::class, ['gtin' => 'ABC'], 'upc', 'upc'],
    'standard identification pharmacode' => [StandardIdentificationData::class, ['gtin' => 'ABC'], 'pharmacode', 'pharmacode'],
    'address street'                     => [AddressData::class, ['countryCode' => 'PT', 'addressDetail' => 'Example address'], 'street', 'street'],
    'item name'                          => [ItemData::class, ['description' => 'Item', 'emitterIdentification' => 'SKU'], 'name', 'name'],
    'payment reference'                  => [PaymentData::class, [], 'paymentReference', 'payment reference'],
    'extra field namespace'              => [ExtraFieldData::class, ['name' => 'CustomNote', 'value' => 'Text'], 'namespace', 'namespace'],
    'payee account nib'                  => [PayeeFinancialAccountData::class, ['name' => 'Bank', 'accountNumber' => '12345'], 'nib', 'nib'],
    'party name'                         => [PartyData::class, ['reference' => PartyReference::Receiver], 'name', 'name'],
]);
dataset('blank texts', ['empty' => [''], 'spaces' => ['   '], 'tab and newline' => ["\t\n"]]);
dataset('factories', ['from' => ['from'], 'validateAndCreate' => ['validateAndCreate']]);

it('rejects a supplied empty optional string', function (string $class, array $payload, string $field, string $label, string $empty, string $method): void {
    $input = [...$payload, $field => $empty];

    expect(fn (): Data => $class::$method($input))->toFailValidationOn($field, sprintf('The %s field must have a value.', $label));
})->with('optional string fields')->with('blank texts')->with('factories');

it('keeps an explicit null optional string through the constructor', function (string $class, array $payload, string $field): void {
    expect(new $class(...[...$payload, $field => null])->{$field})->toBeNull();
})->with('optional string fields');

it('keeps an explicit null optional string through the factories', function (string $class, array $payload, string $field, string $label, string $method): void {
    $input = [...$payload, $field => null];

    expect($class::$method($input)->{$field})->toBeNull();
})->with('optional string fields')->with('factories');

it('leaves an absent optional string null through the factories', function (string $class, array $payload, string $field, string $label, string $method): void {
    expect($class::$method($payload)->{$field})->toBeNull();
})->with('optional string fields')->with('factories');
