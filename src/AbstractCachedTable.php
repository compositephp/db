<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\Exceptions\DbException;
use Cycle\Database\DatabaseProviderInterface;
use Psr\SimpleCache\CacheInterface;

abstract class AbstractCachedTable extends AbstractTable
{
    protected const CACHE_VERSION = 1;

    public function __construct(
        DatabaseProviderInterface $databaseProvider,
        protected CacheInterface $cache,
    ) {
        parent::__construct($databaseProvider);
    }

    /**
     * @return string[]
     */
    abstract protected function getFlushCacheKeys(AbstractEntity $entity): array;

    /**
     * @throws \Throwable
     */
    public function save(AbstractEntity &$entity): void
    {
        $this->transaction(function () use (&$entity) {
            $cacheKeys = $this->collectCacheKeysByEntity($entity);
            parent::save($entity);
            if ($cacheKeys && !$this->cache->deleteMultiple(array_unique($cacheKeys))) {
                throw new DbException('Failed to flush cache keys');
            }
        });
    }

    /**
     * @param AbstractEntity[] $entities
     * @return  AbstractEntity[]
     * @throws \Throwable
     */
    public function saveMany(array $entities): array
    {
        return $this->transaction(function() use ($entities) {
            $cacheKeys = [];
            foreach ($entities as $entity) {
                $cacheKeys = array_merge($cacheKeys, $this->collectCacheKeysByEntity($entity));
            }
            foreach ($entities as $entity) {
                parent::save($entity);
            }
            if ($cacheKeys && !$this->cache->deleteMultiple(array_unique($cacheKeys))) {
                throw new DbException('Failed to flush cache keys');
            }
            return $entities;
        });
    }

    /**
     * @throws \Throwable
     */
    public function delete(AbstractEntity &$entity): void
    {
        $this->transaction(function () use (&$entity) {
            $cacheKeys = $this->collectCacheKeysByEntity($entity);
            parent::delete($entity);
            if ($cacheKeys && !$this->cache->deleteMultiple(array_unique($cacheKeys))) {
                throw new DbException('Failed to flush cache keys');
            }
        });
    }

    /**
     * @param AbstractEntity[] $entities
     * @throws \Throwable
     */
    public function deleteMany(array $entities): bool
    {
        return $this->transaction(function() use ($entities) {
            $cacheKeys = [];
            foreach ($entities as $entity) {
                $cacheKeys = array_merge($cacheKeys, $this->collectCacheKeysByEntity($entity));
            }
            foreach ($entities as $entity) {
                parent::delete($entity);
            }
            if ($cacheKeys && !$this->cache->deleteMultiple(array_unique($cacheKeys))) {
                throw new DbException('Failed to flush cache keys');
            }
            return true;
        });
    }

    /**
     * @param AbstractEntity $entity
     * @return string[]
     */
    private function collectCacheKeysByEntity(AbstractEntity $entity): array
    {
        $keys = $this->getFlushCacheKeys($entity);
        if (!$entity->isNew() || !$this->getSchema()->hasAutoIncrementPrimaryKey()) {
            $keys[] = $this->getOneCacheKey($entity);
        }
        return $keys;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findByPkCachedInternal(mixed $pk, null|int|\DateInterval $ttl = null): ?array
    {
        return $this->findOneCachedInternal($this->getPkCondition($pk), $ttl);
    }

    /**
     * @param array<string, mixed> $condition
     * @param int|\DateInterval|null $ttl
     * @return array<string, mixed>|null
     */
    protected function findOneCachedInternal(array $condition, null|int|\DateInterval $ttl = null): ?array
    {
        return $this->getCached(
            $this->getOneCacheKey($condition),
            fn() => $this->findOneInternal($condition),
            $ttl,
        ) ?: null;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, string>|string $orderBy see \Cycle\Database\Query\SelectQuery::orderBy for format
     * @return array<string, mixed>[]
     */
    protected function findAllCachedInternal(
        array $condition = [],
        array|string $orderBy = [],
        ?int $limit = null,
        null|int|\DateInterval $ttl = null,
    ): array
    {
        return $this->getCached(
            $this->getListCacheKey($condition, $orderBy, $limit),
            fn() => $this->findAllInternal($condition, $orderBy, $limit),
            $ttl,
        );
    }

    /**
     * @param array<string, mixed> $condition
     */
    protected function countAllCachedInternal(array $condition = [], null|int|\DateInterval $ttl = null): int
    {
        return (int)$this->getCached(
            $this->getCountCacheKey($condition),
            fn() => $this->countAllInternal($condition),
            $ttl,
        );
    }

    protected function getCached(string $cacheKey, callable $dataCallback, null|int|\DateInterval $ttl = null): mixed
    {
        $data = $this->cache->get($cacheKey);
        if ($data !== null) {
            return $data;
        }
        $data = $dataCallback();
        if ($data !== null) {
            $this->cache->set($cacheKey, $data, $ttl);
        }
        return $data;
    }

    protected function findMultiCachedInternal(array $ids, null|int|\DateInterval $ttl = null): array
    {
        $result = $cacheKeys = $foundIds = [];
        foreach ($ids as $id) {
            $cacheKey = $this->getOneCacheKey($id);
            $cacheKeys[$cacheKey] = $id;
        }
        $cache = $this->cache->getMultiple(array_keys($cacheKeys));
        foreach ($cache as $cacheKey => $cachedRow) {
            $result[] = $cachedRow;
            if (empty($cacheKeys[$cacheKey])) {
                continue;
            }
            $foundIds[] = $cacheKeys[$cacheKey];
        }
        $ids = array_diff($ids, $foundIds);
        foreach ($ids as $id) {
            if ($row = $this->findOneCachedInternal($id, $ttl)) {
                $result[] = $row;
            }
        }
        return $result;
    }

    protected function getOneCacheKey(string|int|array|AbstractEntity $keyOrEntity): string
    {
        if (!is_array($keyOrEntity)) {
            $condition = $this->getPkCondition($keyOrEntity);
        } else {
            $condition = $keyOrEntity;
        }
        return $this->buildCacheKey('o', $condition ?: 'one');
    }

    protected function getListCacheKey(array $condition = [], array|string $orderBy = [], ?int $limit = null): string
    {
        return $this->buildCacheKey(
            'l',
            $condition ?: 'all',
            $orderBy ? ['ob' => $orderBy] : null,
            $limit ? ['limit' => $limit] : null,
        );
    }

    protected function getCountCacheKey(array $condition = []): string
    {
        return $this->buildCacheKey(
            'c',
            $condition ?: 'all',
        );
    }

    protected function buildCacheKey(mixed ...$parts): string
    {
        $parts = array_filter($parts);
        if ($parts) {
            $formattedParts = [];
            foreach ($parts as $part) {
                if ($part === '' || $part === 0) continue;
                if (is_array($part)) {
                    $formattedParts[] = $this->formatArray($part);
                } else {
                    $formattedParts[] = strval($part);
                }
            }
            $key = implode('.', $formattedParts);
        } else {
            $key = 'all';
        }
        $key = implode('.', [
            $this->getDatabaseName(),
            $this->getTableName(),
            'v' . static::CACHE_VERSION,
            $key
        ]);
        if (strlen($key) > 64) {
            $key = sha1($key);
        }
        return $key;
    }

    private function formatArray(array $array): string
    {
        $string = json_encode($array);
        $string = str_replace([':', ',', '>', '<'], ['_', '_', 'gt', 'lt'], $string);
        return preg_replace('/[^a-zA-Z0-9_]/', '', $string);
    }
}
