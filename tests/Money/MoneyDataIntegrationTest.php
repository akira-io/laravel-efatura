<?php

declare(strict_types=1);

use Akira\Efatura\Casts\BigDecimalCast;
use Akira\Efatura\Tests\Fixtures\FiscalValuesData;
use Akira\Efatura\Transformers\BigDecimalTransformer;
use Akira\Efatura\Transformers\MoneyTransformer;
use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

it('casts and serializes exact fiscal values through real Spatie Data', function (): void {
    $data = FiscalValuesData::from([
        'payable'       => '1.235',
        'unitPrice'     => '1.23456',
        'exchangeRate'  => '123.45678',
        'taxPercentage' => '15.125',
    ]);

    expect((string) $data->payable->getAmount())->toBe('1.24')
        ->and($data->payable->getCurrency()->getCurrencyCode())->toBe('CVE')
        ->and((string) $data->unitPrice->getAmount())->toBe('1.23456')
        ->and((string) $data->exchangeRate)->toBe('123.45678')
        ->and((string) $data->taxPercentage)->toBe('15.125')
        ->and($data->toArray())->toBe([
            'payable'       => '1.24',
            'unitPrice'     => '1.23456',
            'exchangeRate'  => '123.45678',
            'taxPercentage' => '15.125',
        ]);
});

it('rejects floats, scientific notation and excess scale at the Data boundary', function (array $input, string $field, string $message): void {
    expect(fn (): FiscalValuesData => FiscalValuesData::from($input))
        ->toThrow(function (ValidationException $exception) use ($field, $message): void {
            expect($exception->errors())->toBe([$field => [$message]]);
        });
})->with([
    'float payable'            => [['payable' => 1.23, 'unitPrice' => '1.2', 'exchangeRate' => '1', 'taxPercentage' => '15'], 'payable', 'Money must be an integer, decimal string, or Money value.'],
    'scientific exchange rate' => [['payable' => '1', 'unitPrice' => '1.2', 'exchangeRate' => '1e3', 'taxPercentage' => '15'], 'exchangeRate', 'Value must be a plain decimal number.'],
    'unit price scale'         => [['payable' => '1', 'unitPrice' => '1.234567', 'exchangeRate' => '1', 'taxPercentage' => '15'], 'unitPrice', 'Value exceeds the allowed decimal precision.'],
    'exchange rate scale'      => [['payable' => '1', 'unitPrice' => '1.2', 'exchangeRate' => '1.234567', 'taxPercentage' => '15'], 'exchangeRate', 'Value exceeds the allowed decimal precision.'],
    'tax percentage scale'     => [['payable' => '1', 'unitPrice' => '1.2', 'exchangeRate' => '1', 'taxPercentage' => '15.1234'], 'taxPercentage', 'Value exceeds the allowed decimal precision.'],
]);

it('rejects a float at the decimal cast boundary', function (): void {
    $property = resolve(DataConfig::class)->getDataClass(FiscalValuesData::class)->properties['exchangeRate'];
    $creation = FiscalValuesData::factory()->get();

    expect(fn (): BigDecimal => new BigDecimalCast()->cast($property, 1.2, [], $creation))
        ->toFailValidationOn('exchangeRate', 'Value must be a plain decimal number.');
});

it('rejects invalid values at the transformer boundary', function (Transformer $transformer, string $field, mixed $value, string $message): void {
    $property       = resolve(DataConfig::class)->getDataClass(FiscalValuesData::class)->properties[$field];
    $transformation = new TransformationContext;

    expect(fn (): mixed => $transformer->transform($property, $value, $transformation))
        ->toFailValidationOn($field, $message);
})->with([
    'float decimal'          => [fn (): Transformer => new BigDecimalTransformer, 'exchangeRate', 1.2, 'Value must be a plain decimal number.'],
    'decimal scale'          => [fn (): Transformer => new BigDecimalTransformer(2), 'exchangeRate', BigDecimal::of('1.234'), 'Value exceeds the allowed decimal precision.'],
    'money as string'        => [fn (): Transformer => new MoneyTransformer, 'payable', '1.00', 'Money must be an integer, decimal string, or Money value.'],
    'strict money precision' => [
        fn (): Transformer => new MoneyTransformer(2, false),
        'payable',
        Money::of('1.234', 'CVE', new CustomContext(3)),
        'Value exceeds the allowed decimal precision.',
    ],
]);
