<?php

declare(strict_types=1);

use Akira\Efatura\Casts\FiscalDateCast;
use Akira\Efatura\Casts\MoneyCast;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\TotalsData;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Support\DataConfig;

beforeEach(function (): void {
    $this->app->register(LaravelDataServiceProvider::class);
});

it('casts and transforms CVE amounts through one attribute', function (): void {
    $property = resolve(DataConfig::class)->getDataClass(TotalsData::class)->properties['payableAmount'];
    $totals   = TotalsData::from(['priceExtensionTotalAmount' => '1', 'netTotalAmount' => '1', 'taxTotalAmount' => '0', 'payableAmount' => '1.12345']);

    expect($property->cast)->toBeInstanceOf(MoneyCast::class)
        ->and($property->transformer)->toBeInstanceOf(MoneyCast::class)
        ->and($totals->toArray()['payableAmount'])->toBe('1.12345');
});

it('casts and transforms fiscal dates through one attribute', function (): void {
    $property = resolve(DataConfig::class)->getDataClass(DocumentHeaderData::class)->properties['issueTime'];
    $header   = DocumentHeaderData::from(['issueDate' => '2026-10-02', 'issueTime' => '09:30:00', 'ledCode' => 1]);

    expect($property->cast)->toBeInstanceOf(FiscalDateCast::class)
        ->and($property->transformer)->toBeInstanceOf(FiscalDateCast::class)
        ->and($header->toArray())->toMatchArray(['issueDate' => '2026-10-02', 'issueTime' => '09:30:00']);
});
