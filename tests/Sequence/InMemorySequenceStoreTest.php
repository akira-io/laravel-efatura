<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\SequenceException;
use Akira\Efatura\Sequence\InMemorySequenceStore;
use Akira\Efatura\Sequence\SequenceScope;
use Akira\Efatura\Tests\Support\SequenceFixtures as S;

it('counts each scope on its own from one', function (SequenceScope $otherScope): void {
    $store = new InMemorySequenceStore;

    expect($store->next(S::scope()))->toBe(1)
        ->and($store->next(S::scope()))->toBe(2)
        ->and($store->next($otherScope))->toBe(1)
        ->and($store->next(S::scope()))->toBe(3)
        ->and($store->current($otherScope))->toBe(1);
})->with(fn (): array => S::otherScopes());

it('reports zero for a scope it never numbered', function (): void {
    expect(new InMemorySequenceStore()->current(S::scope()))->toBe(0);
});

it('allocates the last fiscal number and then refuses the scope', function (): void {
    $store = new InMemorySequenceStore;
    $store->continueAfter(S::scope(), 999_999_998);

    expect($store->next(S::scope()))->toBe(999_999_999)
        ->and(fn (): int => $store->next(S::scope()))->toThrow(SequenceException::class, 'sequence.exhausted')
        ->and($store->current(S::scope()))->toBe(999_999_999);
});

it('names the exhausted scope without marking it retryable', function (): void {
    $store = new InMemorySequenceStore;
    $store->continueAfter(S::scope(), 999_999_999);

    expect(fn (): int => $store->next(S::scope()))->toThrow(function (SequenceException $exception): void {
        expect($exception->errorCode)->toBe('sequence.exhausted')
            ->and($exception->retryable)->toBeFalse()
            ->and($exception->getPrevious())->toBeNull()
            ->and($exception->context)->toBe(['emitterTaxId' => '100200300', 'fiscalYear' => 2026, 'ledCode' => 1, 'documentTypeCode' => 1]);
    });
});
