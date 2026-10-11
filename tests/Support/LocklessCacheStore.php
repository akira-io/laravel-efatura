<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Store;

final readonly class LocklessCacheStore implements Store
{
    public function __construct(private ArrayStore $store = new ArrayStore) {}

    public function get($key): mixed
    {
        return $this->store->get($key);
    }

    public function many(array $keys): array
    {
        return $this->store->many($keys);
    }

    public function put($key, $value, $seconds): bool
    {
        return $this->store->put($key, $value, $seconds);
    }

    public function putMany(array $values, $seconds): bool
    {
        return $this->store->putMany($values, $seconds);
    }

    public function increment($key, $value = 1): int|bool
    {
        return $this->store->increment($key, $value);
    }

    public function decrement($key, $value = 1): int|bool
    {
        return $this->store->decrement($key, $value);
    }

    public function forever($key, $value): bool
    {
        return $this->store->forever($key, $value);
    }

    public function touch($key, $seconds): bool
    {
        return $this->store->touch($key, $seconds);
    }

    public function forget($key): bool
    {
        return $this->store->forget($key);
    }

    public function flush(): bool
    {
        return $this->store->flush();
    }

    public function getPrefix(): string
    {
        return $this->store->getPrefix();
    }
}
