<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\EfaturaValidationException;
use Akira\Efatura\Money\BigDecimalCast;
use Akira\Efatura\Money\BigDecimalTransformer;
use Akira\Efatura\Money\MoneyCast;
use Akira\Efatura\Money\MoneyTransformer;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\Transformation\TransformationContext;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
});

final class FiscalValuesData extends Data
{
    public function __construct(
        #[WithCast(MoneyCast::class, 'CVE')]
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $payable,
        #[WithCast(MoneyCast::class, 'CVE', 5, false)]
        #[WithTransformer(MoneyTransformer::class, 5, false)]
        public readonly Money $unitPrice,
        #[WithCast(BigDecimalCast::class, 5)]
        #[WithTransformer(BigDecimalTransformer::class, 5)]
        public readonly BigDecimal $exchangeRate,
        #[WithCast(BigDecimalCast::class, 3)]
        #[WithTransformer(BigDecimalTransformer::class, 3)]
        public readonly BigDecimal $taxPercentage,
    ) {}
}

it('casts and serializes exact fiscal values through real Spatie Data', function (): void {
    $data = FiscalValuesData::from([
        'payable'       => '1.235',
        'unitPrice'     => '1.23456',
        'exchangeRate'  => '123.45678',
        'taxPercentage' => '15.125',
    ]);

    expect($data->payable)->toBeInstanceOf(Money::class)
        ->and($data->unitPrice)->toBeInstanceOf(Money::class)
        ->and($data->exchangeRate)->toBeInstanceOf(BigDecimal::class)
        ->and($data->toArray())->toBe([
            'payable'       => '1.24',
            'unitPrice'     => '1.23456',
            'exchangeRate'  => '123.45678',
            'taxPercentage' => '15.125',
        ]);
});

it('rejects floats and scientific notation at the Data boundary', function (array $input): void {
    expect(fn (): FiscalValuesData => FiscalValuesData::from($input))
        ->toThrow(EfaturaValidationException::class);
})->with([
    [['payable' => 1.23, 'unitPrice' => '1.2', 'exchangeRate' => '1', 'taxPercentage' => '15']],
    [['payable' => '1', 'unitPrice' => '1.2', 'exchangeRate' => '1e3', 'taxPercentage' => '15']],
    [['payable' => '1', 'unitPrice' => '1.234567', 'exchangeRate' => '1', 'taxPercentage' => '15']],
    [['payable' => '1', 'unitPrice' => '1.2', 'exchangeRate' => '1.234567', 'taxPercentage' => '15']],
    [['payable' => '1', 'unitPrice' => '1.2', 'exchangeRate' => '1', 'taxPercentage' => '15.1234']],
]);

it('rejects invalid cast and transformer values at their public boundaries', function (): void {
    $properties     = resolve(DataConfig::class)->getDataClass(FiscalValuesData::class)->properties;
    $creation       = FiscalValuesData::factory()->get();
    $transformation = new TransformationContext;

    expect(fn (): BigDecimal => (new BigDecimalCast)->cast($properties['exchangeRate'], 1.2, [], $creation))
        ->toThrow(EfaturaValidationException::class)
        ->and(fn (): string => (new BigDecimalTransformer)->transform($properties['exchangeRate'], 1.2, $transformation))
        ->toThrow(EfaturaValidationException::class)
        ->and(fn (): string => (new MoneyTransformer)->transform($properties['payable'], '1.00', $transformation))
        ->toThrow(EfaturaValidationException::class);
});
