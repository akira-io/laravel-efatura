<?php

declare(strict_types=1);

use Akira\Efatura\Contracts\SequenceStore;
use Akira\Efatura\Exceptions\SequenceException;
use Akira\Efatura\Sequence\DatabaseSequenceStore;
use Akira\Efatura\Tests\Support\SequenceFixtures as S;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir() . '/efatura-lock-' . Str::uuid()->toString();
    $this->database  = $this->directory . '/sequences.sqlite';
    new Filesystem()->ensureDirectoryExists($this->directory);
    touch($this->database);
    config()->set('database.connections.shared', ['driver' => 'sqlite', 'database' => $this->database, 'prefix' => '', 'busy_timeout' => 1]);
    S::useConnection('shared');
    $this->holder = new PDO('sqlite:' . $this->database);
    $this->holder->exec('BEGIN EXCLUSIVE TRANSACTION');
});

afterEach(function (): void {
    $this->holder = null;
    DB::purge('shared');
    new Filesystem()->deleteDirectory($this->directory);
});

it('retries a locked sequence a bounded number of times before reporting a retryable outage', function (): void {
    $attempts = 0;
    Event::listen(TransactionBeginning::class, function () use (&$attempts): void {
        $attempts++;
    });

    expect(fn (): int => resolve(SequenceStore::class)->next(S::scope()))->toThrow(function (SequenceException $exception): void {
        expect($exception->errorCode)->toBe('sequence.unavailable')
            ->and($exception->retryable)->toBeTrue()
            ->and($exception->getPrevious()?->getMessage())->toContain('database is locked');
    })->and($attempts)->toBe(DatabaseSequenceStore::ATTEMPTS);
});

it('keeps the number of attempts at five', function (): void {
    expect(DatabaseSequenceStore::ATTEMPTS)->toBe(5);
});

it('reserves when the lock is released before the attempts run out', function (): void {
    $attempts = 0;
    Event::listen(TransactionBeginning::class, function () use (&$attempts): void {
        if (++$attempts === 3) {
            $this->holder->exec('ROLLBACK');
        }
    });

    expect(resolve(SequenceStore::class)->next(S::scope()))->toBe(1)
        ->and($attempts)->toBe(3);
});

it('hands out the first number once the lock is released', function (): void {
    $store = resolve(SequenceStore::class);

    expect(fn (): int => $store->next(S::scope()))->toThrow(SequenceException::class, 'sequence.unavailable');

    $this->holder->exec('ROLLBACK');

    expect($store->next(S::scope()))->toBe(1);
});

it('refuses a reservation inside the transaction of its caller before it meets the lock', function (): void {
    $store = resolve(SequenceStore::class);

    expect(fn (): int => DB::connection('shared')->transaction(fn (): int => $store->next(S::scope())))
        ->toThrow(SequenceException::class, 'sequence.inside_transaction');
});
