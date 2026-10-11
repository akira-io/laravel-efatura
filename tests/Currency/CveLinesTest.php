<?php

declare(strict_types=1);

use Akira\Efatura\Currency\ConvertedLine;
use Akira\Efatura\Currency\CveLines;
use Akira\Efatura\Data\DiscountData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Brick\Math\BigDecimal;

it('refuses a global amount discount its lines were never allocated before moving any cent', function (string $discount, int $lines): void {
    $source = array_fill(0, $lines, LineItemData::from(F::linePayload(['price' => '10', 'priceExtension' => '10', 'netTotal' => '10'])));
    $global = DiscountData::from(['value' => $discount, 'valueType' => 'A']);

    expect(C::failure(fn (): array => new CveLines(BigDecimal::one())->convert($source, $global)))->toBe(ExchangeRateFailure::InvoiceInvalid);
})->with([
    'one line and two cents'     => ['0.02', 1],
    'three lines and four cents' => ['0.04', 3],
    'a discount of a billion'    => ['1000000000', 2],
]);

it('moves as many residual cents as there are lines', function (): void {
    $source = array_fill(0, 3, LineItemData::from(F::linePayload(['price' => '10', 'priceExtension' => '10', 'netTotal' => '10'])));
    $global = DiscountData::from(['value' => '0.03', 'valueType' => 'A']);

    $nets = array_map(static fn (ConvertedLine $line): string => $line->payload['netTotal'], new CveLines(BigDecimal::one())->convert($source, $global));

    expect($nets)->toBe(['9.99', '9.99', '9.99']);
});
