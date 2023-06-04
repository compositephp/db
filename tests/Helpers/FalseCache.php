<?php declare(strict_types=1);

namespace Composite\DB\Tests\Helpers;

use Psr\SimpleCache\CacheInterface;

class FalseCache implements CacheInterface
{

    public function get(string $key, mixed $default = null): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return false;
    }

    public function delete(string $key): bool
    {
        return false;
    }

    public function clear(): bool
    {
        return false;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return [];
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        return false;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return false;
    }

    public function has(string $key): bool
    {
        return false;
    }
}
