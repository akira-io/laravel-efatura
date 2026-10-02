<?php

declare(strict_types=1);

use Akira\Efatura\Enums\TaxType;
use Akira\Efatura\Support\ValidationPayload;
use Brick\Money\Money;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Support\Validation\ValidationPath;

it('reads an enum the payload already holds as a case', function (): void {
    $context = new ValidationContext(['taxTypeCode' => TaxType::ValueAddedTax], [], ValidationPath::create());

    expect(ValidationPayload::enum($context, 'taxTypeCode', TaxType::class))->toBe(TaxType::ValueAddedTax);
});

it('reads the amount of a Money value as a decimal', function (): void {
    $context = new ValidationContext(['amount' => Money::of('12.5', 'CVE')], [], ValidationPath::create());

    expect((string) ValidationPayload::decimal($context, 'amount'))->toBe('12.50');
});
