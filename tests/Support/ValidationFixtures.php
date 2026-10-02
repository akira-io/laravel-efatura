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
            'type'      => DocumentType::ELECTRONIC_INVOICE,
            'issueDate' => '2026-02-08',
            'emitter'   => [
                'nif'  => '100200300',
                'name' => 'Emitter',
            ],
            'receiver' => [
                'nif'  => '900800700',
                'name' => 'Receiver',
            ],
            'lines' => [
                [
                    'description' => 'Item',
                    'quantity'    => 1,
                    'unitPrice'   => 1000.0,
                    'total'       => 1000.0,
                    'taxes'       => [
                        [
                            'type'   => 'IVA',
                            'rate'   => 15.0,
                            'amount' => 150.0,
                        ],
                    ],
                ],
            ],
            'totals' => [
                'subtotal'   => 1000.0,
                'taxTotal'   => 150.0,
                'grandTotal' => 1150.0,
            ],
        ])->replaceRecursive($overrides)->all();
    }
}
