<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\SequenceFixtures as S;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir() . '/efatura-concurrency-' . Str::uuid()->toString();
    $this->database  = $this->directory . '/sequences.sqlite';
    new Filesystem()->ensureDirectoryExists($this->directory);
    touch($this->database);
    config()->set('database.connections.shared', ['driver' => 'sqlite', 'database' => $this->database, 'prefix' => '', 'busy_timeout' => 5000]);
    config()->set('efatura.database.connection', 'shared');
    S::migrate();
});

afterEach(function (): void {
    new Filesystem()->deleteDirectory($this->directory);
});

it('never hands the same number to two processes', function (): void {
    $command = [PHP_BINARY, '-d', 'display_errors=stderr', __DIR__ . '/../Fixtures/sequence-worker.php', $this->database, '50'];
    $workers = collect(range(1, 8))->map(fn (): Process => new Process($command));
    $workers->each(fn (Process $worker): null => $worker->start());

    $exitCodes = $workers->map(fn (Process $worker): int => $worker->wait())->unique()->all();
    $numbers   = $workers->flatMap(fn (Process $worker): array => array_map(intval(...), explode(PHP_EOL, trim($worker->getOutput()))))
        ->sort()->values()->all();

    expect($exitCodes)->toBe([0])
        ->and($numbers)->toBe(range(1, 400));
});
