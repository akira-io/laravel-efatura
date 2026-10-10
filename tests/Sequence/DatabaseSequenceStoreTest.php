<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Exceptions\SequenceException;
use Akira\Efatura\Sequence\DatabaseSequenceStore;
use Akira\Efatura\Sequence\SequenceScope;
use Akira\Efatura\Tests\Support\SequenceFixtures as S;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-02T12:00:00-01:00');
    config()->set('database.connections.ledger', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
});

it('is the sequence store the container resolves', function (): void {
    expect(resolve(SequenceStore::class))->toBeInstanceOf(DatabaseSequenceStore::class);
});

it('counts each scope on its own from one', function (SequenceScope $otherScope): void {
    S::migrate();
    $store = resolve(SequenceStore::class);

    expect($store->next(S::scope()))->toBe(1)
        ->and($store->next(S::scope()))->toBe(2)
        ->and($store->next($otherScope))->toBe(1)
        ->and($store->next(S::scope()))->toBe(3)
        ->and(DB::table('efatura_sequences')->count())->toBe(2);
})->with(fn (): array => S::otherScopes());

it('stores the scope, the last number and the reservation time', function (): void {
    S::migrate();
    resolve(SequenceStore::class)->next(S::scope());
    CarbonImmutable::setTestNow('2026-10-02T12:05:00-01:00');
    resolve(SequenceStore::class)->next(S::scope());

    expect((array) DB::table('efatura_sequences')->first())->toBe([
        'emitter_tax_id'     => '100200300',
        'fiscal_year'        => 2026,
        'led_code'           => 1,
        'document_type_code' => 1,
        'current_number'     => 2,
        'created_at'         => '2026-10-02 12:00:00',
        'updated_at'         => '2026-10-02 12:05:00',
    ]);
});

it('reserves on the configured connection and table', function (): void {
    S::useConnection('ledger', 'fiscal_counters');

    expect(resolve(SequenceStore::class)->next(S::scope()))->toBe(1)
        ->and(DB::connection('ledger')->table('fiscal_counters')->value('current_number'))->toBe(1)
        ->and(Schema::connection('testing')->hasTable('fiscal_counters'))->toBeFalse();
});

it('inherits the default database connection when none is configured', function (): void {
    config()->set('database.default', 'ledger');
    S::useConnection(null);

    expect(resolve(SequenceStore::class)->next(S::scope()))->toBe(1)
        ->and(DB::connection('ledger')->table('efatura_sequences')->value('current_number'))->toBe(1)
        ->and(Schema::connection('testing')->hasTable('efatura_sequences'))->toBeFalse();
});

it('allocates the last fiscal number and then refuses the scope without consuming it', function (): void {
    S::migrate();
    DB::table('efatura_sequences')->insert(['emitter_tax_id' => '100200300', 'fiscal_year' => 2026, 'led_code' => 1, 'document_type_code' => 1,
        'current_number'                                     => 999_999_998]);
    $store = resolve(SequenceStore::class);

    expect($store->next(S::scope()))->toBe(999_999_999)
        ->and(fn (): int => $store->next(S::scope()))->toThrow(SequenceException::class, 'sequence.exhausted')
        ->and(DB::table('efatura_sequences')->value('current_number'))->toBe(999_999_999);
});

it('reports a missing table as a retryable outage without the sql', function (): void {
    expect(fn (): int => resolve(SequenceStore::class)->next(S::scope()))->toThrow(function (SequenceException $exception): void {
        expect($exception->errorCode)->toBe('sequence.unavailable')
            ->and($exception->getMessage())->toBe('sequence.unavailable')
            ->and($exception->retryable)->toBeTrue()
            ->and($exception->getPrevious())->toBeInstanceOf(QueryException::class)
            ->and($exception->context)->toBe(['emitterTaxId' => '100200300', 'fiscalYear' => 2026, 'ledCode' => 1, 'documentTypeCode' => 1]);
    });
});

it('follows the transaction of its caller', function (): void {
    S::migrate();
    $store = resolve(SequenceStore::class);

    expect(fn (): mixed => DB::transaction(function () use ($store): void {
        $store->next(S::scope());

        throw new RuntimeException('caller failed');
    }))->toThrow(RuntimeException::class, 'caller failed')
        ->and($store->next(S::scope()))->toBe(1);
});
