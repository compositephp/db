<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\Exceptions\DbException;
use Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity;
use Composite\Entity\AbstractEntity;
use Psr\SimpleCache\CacheInterface;
use Ramsey\Uuid\UuidInterface;

abstract class AbstractCachedTable extends AbstractTable
{
    protected const CACHE_VERSION = 1;

    public function __construct(
        protected CacheInterface $cache,
    ) {
        parent::__construct();
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
        $cacheKeys = $this->collectCacheKeysByEntity($entity);
        parent::save($entity);
        if ($cacheKeys) {
            $this->cache->deleteMultiple($cacheKeys);
        }
    }

    /**
     * @param AbstractEntity[] $entities
     * @throws \Throwable
     */
    public function saveMany(array $entities): void
    {
        $cacheKeys = [];
        foreach ($entities as $entity) {
            $cacheKeys = array_merge($cacheKeys, $this->collectCacheKeysByEntity($entity));
        }
        parent::saveMany($entities);
        if ($cacheKeys) {
            $this->cache->deleteMultiple(array_unique($cacheKeys));
        }
    }

    /**
     * @throws \Throwable
     */
    public function delete(AbstractEntity &$entity): void
    {
        $cacheKeys = $this->collectCacheKeysByEntity($entity);
        parent::delete($entity);
        if ($cacheKeys) {
            $this->cache->deleteMultiple($cacheKeys);
        }
    }

    /**
     * @param AbstractEntity[] $entities
     * @throws \Throwable
     */
    public function deleteMany(array $entities): void
    {
        $cacheKeys = [];
        foreach ($entities as $entity) {
            $cacheKeys = array_merge($cacheKeys, $this->collectCacheKeysByEntity($entity));
            parent::delete($entity);
        }
        parent::deleteMany($entities);
        if ($cacheKeys) {
            $this->cache->deleteMultiple($cacheKeys);
        }
    }

    /**
     * @param AbstractEntity $entity
     * @return string[]
     */
    private function collectCacheKeysByEntity(AbstractEntity $entity): array
    {
        $keys = $this->getFlushCacheKeys($entity);
        if (!$entity->isNew() || !$this->getConfig()->autoIncrementKey) {
            $keys[] = $this->getOneCacheKey($entity);
        }
        return array_unique($keys);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function _findByPkCached(mixed $pk, null|int|\DateInterval $ttl = null): ?array
    {
        return $this->_findOneCached($this->getPkCondition($pk), $ttl);
    }

    /**
     * @param array<string, mixed> $condition
     * @param int|\DateInterval|null $ttl
     * @return array<string, mixed>|null
     */
    protected function _findOneCached(array $condition, null|int|\DateInterval $ttl = null): ?array
    {
        return $this->getCached(
            $this->getOneCacheKey($condition),
            fn() => $this->_findOne($condition),
            $ttl,
        ) ?: null;
    }

    /**
     * @param array<string, mixed>|Where $where
     * @param array<string, string>|string $orderBy
     * @return array<string, mixed>[]
     */
    protected function _findAllCached(
        array|Where $where = [],
        array|string $orderBy = [],
        ?int $limit = null,
        null|int|\DateInterval $ttl = null,
    ): array
    {
        return $this->getCached(
            $this->getListCacheKey($where, $orderBy, $limit),
            fn() => $this->_findAll(where: $where, orderBy: $orderBy, limit: $limit),
            $ttl,
        );
    }

    /**
     * @param array<string, mixed>|Where $where
     */
    protected function _countByAllCached(
        array|Where $where = [],
        null|int|\DateInterval $ttl = null,
    ): int
    {
        return (int)$this->getCached(
            $this->getCountCacheKey($where),
            fn() => $this->_countAll(where: $where),
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

    /**
     * @param mixed[] $ids
     * @param int|\DateInterval|null $ttl
     * @return array<array<string, mixed>>
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function _findMultiCached(array $ids, null|int|\DateInterval $ttl = null): array
    {
        $result = $cacheKeys = $foundIds = [];
        foreach ($ids as $id) {
            $cacheKey = $this->getOneCacheKey($id);
            $cacheKeys[$cacheKey] = $id;
        }
        $cache = $this->cache->getMultiple(array_keys($cacheKeys));
        foreach ($cache as $cacheKey => $cachedRow) {
            if ($cachedRow === null) {
                continue;
            }
            if (isset($cacheKeys[$cacheKey])) {
                $result[] = $cachedRow;
                $foundIds[] = $cacheKeys[$cacheKey];
            }
        }
        $ids = array_diff($ids, $foundIds);
        foreach ($ids as $id) {
            if ($row = $this->_findByPkCached($id, $ttl)) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /**
     * @param string|int|array<string, mixed>|AbstractEntity $keyOrEntity
     */
    protected function getOneCacheKey(string|int|array|AbstractEntity|UuidInterface $keyOrEntity): string
    {
        if (!is_array($keyOrEntity)) {
            $condition = $this->getPkCondition($keyOrEntity);
        } else {
            $condition = $keyOrEntity;
        }
        return $this->buildCacheKey('o', $condition ?: 'one');
    }

    /**
     * @param array<string, mixed>|Where $where
     * @param array<string, string>|string $orderBy
     */
    protected function getListCacheKey(
        array|Where $where = [],
        array|string $orderBy = [],
        ?int $limit = null
    ): string
    {
        $wherePart = is_array($where) ? $where : $this->prepareWhereKey($where);
        return $this->buildCacheKey(
            'l',
            $wherePart ?: 'all',
            $orderBy ? ['ob' => $orderBy] : null,
            $limit ? ['limit' => $limit] : null,
        );
    }

    /**
     * @param array<string, mixed>|Where $where
     */
    protected function getCountCacheKey(
        array|Where $where = [],
    ): string
    {
        $wherePart = is_array($where) ? $where : $this->prepareWhereKey($where);
        return $this->buildCacheKey(
            'c',
            $wherePart ?: 'all',
        );
    }

    protected function buildCacheKey(mixed ...$parts): string
    {
        $parts = array_filter($parts);
        if ($parts) {
            $formattedParts = [];
            foreach ($parts as $part) {
                if (is_array($part)) {
                    $string = json_encode($part, JSON_THROW_ON_ERROR);
                } else {
                    $string = strval($part);
                }
                $formattedParts[] = $this->formatStringForCacheKey($string);
            }
            $key = implode('.', $formattedParts);
        } else {
            $key = 'all';
        }
        $key = implode('.', [
            $this->getConnectionName(),
            $this->getTableName(),
            'v' . static::CACHE_VERSION,
            $key
        ]);
        if (strlen($key) > 64) {
            $key = sha1($key);
        }
        return $key;
    }

    private function formatStringForCacheKey(string $string): string
    {
        $string = mb_strtolower($string);
        $string = str_replace(['!=', '<>', '>', '<', '='], ['_not_', '_not_', '_gt_', '_lt_', '_eq_'], $string);
        $string =  (string)preg_replace('/\W/', '_', $string);
        return trim((string)preg_replace('/_+/', '_', $string), '_');
    }

    private function prepareWhereKey(Where $where): string
    {
        return str_replace(
            array_map(fn (string $key): string => ':' . $key, array_keys($where->params)),
            array_values($where->params),
            $where->string,
        );
    }
}
