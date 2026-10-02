<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Enums\DocumentType;
use Illuminate\Validation\ValidationException;

final class ValidationFixtures
{
    public static function assertMessage(callable $callable, string $field, string $message): void
    {
        try {
            $callable();
            expect(false)->toBeTrue();
        } catch (ValidationException $validationException) {
            $errors = $validationException->errors();
            expect($errors[$field][0])->toBe($message);
        }
    }

    public static function invoicePayload(array $overrides = []): array
    {
        return collect([
            'type'      => DocumentType::Invoice,
            'issueDate' => '2026-02-08',
            'emitter'   => [
                'taxId' => ['value' => '100200300', 'countryCode' => 'CV'],
                'name'  => 'Emitter',
            ],
            'receiver' => [
                'taxId' => ['value' => '900800700', 'countryCode' => 'CV'],
                'name'  => 'Receiver',
            ],
            'lines' => [
                [
                    'item'           => ['description' => 'Item', 'emitterIdentification' => 'SKU-1'],
                    'quantity'       => ['value' => '1', 'unitCode' => 'C62'],
                    'price'          => '1000',
                    'priceExtension' => '1000',
                    'netTotal'       => '1000',
                    'taxes'          => [
                        [
                            'taxTypeCode'   => 'IVA',
                            'taxPercentage' => '15',
                            'taxTotal'      => '150',
                        ],
                    ],
                ],
            ],
            'totals' => [
                'priceExtensionTotalAmount' => '1000',
                'netTotalAmount'            => '1000',
                'taxTotalAmount'            => '150',
                'payableAmount'             => '1150',
            ],
        ])->replaceRecursive($overrides)->all();
    }
}
