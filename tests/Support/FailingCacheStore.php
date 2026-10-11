<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Illuminate\Contracts\Cache\Store;
use RuntimeException;
use Throwable;

final class FailingCacheStore implements Store
{
    public int $reads = 0;

    public int $writes = 0;

    /**
     * @param class-string<Throwable> $failure
     */
    public function __construct(private readonly string $failure = RuntimeException::class, private readonly bool $failsReads = true) {}

    public function get($key): mixed
    {
        $this->reads++;

        return $this->failsReads ? throw new ($this->failure)('cache read failed') : null;
    }

    public function many(array $keys): array
    {
        throw new ($this->failure)('cache read failed');
    }

    public function put($key, $value, $seconds): bool
    {
        $this->writes++;

        throw new ($this->failure)('cache write failed');
    }

    public function putMany(array $values, $seconds): bool
    {
        throw new ($this->failure)('cache write failed');
    }

    public function increment($key, $value = 1): int|bool
    {
        throw new ($this->failure)('cache write failed');
    }

    public function decrement($key, $value = 1): int|bool
    {
        throw new ($this->failure)('cache write failed');
    }

    public function forever($key, $value): bool
    {
        throw new ($this->failure)('cache write failed');
    }

    public function touch($key, $seconds): bool
    {
        throw new ($this->failure)('cache write failed');
    }

    public function forget($key): bool
    {
        throw new ($this->failure)('cache write failed');
    }

    public function flush(): bool
    {
        throw new ($this->failure)('cache write failed');
    }

    public function getPrefix(): string
    {
        return '';
    }
}
