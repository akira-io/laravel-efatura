<?php

declare(strict_types=1);

use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\XmlValue;
use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

it('writes decimals in their minimal plain form', function (BigDecimal|Money $value, int $scale, string $expected): void {
    expect(XmlValue::decimal($value, 'totals.payableAmount', $scale))->toBe($expected);
})->with([
    'trailing zeros'         => [BigDecimal::of('100.00000'), 5, '100'],
    'fraction'               => [BigDecimal::of('0.10000'), 5, '0.1'],
    'negative zero'          => [BigDecimal::of('-0.00000'), 5, '0'],
    'five places'            => [BigDecimal::of('1.12345'), 5, '1.12345'],
    'fifteen integer digits' => [BigDecimal::of('123456789012345.12345'), 5, '123456789012345.12345'],
    'percentage'             => [BigDecimal::of('15.500'), 3, '15.5'],
    'signed rounding amount' => [BigDecimal::of('-2.50000'), 5, '-2.5'],
    'money'                  => [Money::of('30000', 'CVE', new CustomContext(5)), 5, '30000'],
    'integer'                => [BigDecimal::of('7'), 5, '7'],
]);

it('never writes an exponent', function (): void {
    expect(XmlValue::decimal(BigDecimal::of('1E+3'), 'totals.payableAmount'))->toBe('1000')
        ->and(XmlValue::decimal(BigDecimal::of('1E-5'), 'totals.payableAmount'))->toBe('0.00001');
});

it('passes an absent decimal through', function (): void {
    expect(XmlValue::decimal(null, 'lines.0.price'))->toBeNull();
});

it('refuses to round a value beyond the scale of its field', function (): void {
    expect(fn (): ?string => XmlValue::decimal(BigDecimal::of('15.123456'), 'lines.0.taxes.0.taxPercentage', 3))
        ->toFailValidationOn('lines.0.taxes.0.taxPercentage', 'Value exceeds the allowed decimal precision.');
});

it('writes instants in Cabo Verde time', function (): void {
    $moment = CarbonImmutable::parse('2026-10-03 00:30:00', 'UTC');

    expect(XmlValue::date($moment, instant: true))->toBe('2026-10-02')
        ->and(XmlValue::time($moment, instant: true))->toBe('23:30:00')
        ->and(XmlValue::dateTime($moment, instant: true))->toBe('2026-10-02T23:30:00');
});

it('writes calendar values without shifting them', function (): void {
    $moment = CarbonImmutable::parse('2026-10-03 00:30:00', 'UTC');

    expect(XmlValue::date($moment))->toBe('2026-10-03')
        ->and(XmlValue::time($moment))->toBe('00:30:00')
        ->and(XmlValue::dateTime($moment))->toBe('2026-10-03T00:30:00');
});

it('passes absent dates through', function (): void {
    expect(XmlValue::date(null))->toBeNull()
        ->and(XmlValue::time(null))->toBeNull()
        ->and(XmlValue::dateTime(null))->toBeNull();
});

it('writes booleans as the schema literals', function (?bool $value, ?string $expected): void {
    expect(XmlValue::boolean($value))->toBe($expected);
})->with([
    'true'   => [true, 'true'],
    'false'  => [false, 'false'],
    'absent' => [null, null],
]);

it('writes integers without leading zeros', function (?int $value, ?string $expected): void {
    expect(XmlValue::integer($value))->toBe($expected);
})->with([
    'document number' => [999999999, '999999999'],
    'led code'        => [1, '1'],
    'absent'          => [null, null],
]);

it('declares the XML structure version apart from the manual version', function (): void {
    expect(Fiscal::XML_SCHEMA_VERSION)->toBe('1.0');
});
