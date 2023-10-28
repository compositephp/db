<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractCachedTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity;
use Composite\DB\Tests\TestStand\Interfaces\IAutoincrementTable;
use Composite\DB\Where;
use Composite\Entity\AbstractEntity;

class TestAutoincrementCachedTable extends AbstractCachedTable implements IAutoincrementTable
{
    public function __construct(\Psr\SimpleCache\CacheInterface $cache)
    {
        parent::__construct($cache);
        (new TestAutoincrementTable)->init();
    }

    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestAutoincrementEntity::schema());
    }

    protected function getFlushCacheKeys(TestAutoincrementEntity|AbstractEntity $entity): array
    {
        $keys = [
            $this->getOneCacheKey(['name' => $entity->name]),
            $this->getListCacheKey(new Where('name = :name', ['name' => $entity->name])),
            $this->getCountCacheKey(new Where('name = :name', ['name' => $entity->name])),
        ];
        $oldName = $entity->getOldValue('name');
        if (!$entity->isNew() && $oldName !== $entity->name) {
            $keys[] = $this->getOneCacheKey(['name' => $oldName]);
            $keys[] = $this->getListCacheKey(new Where('name = :name', ['name' => $oldName]));
            $keys[] = $this->getCountCacheKey(new Where('name = :name', ['name' => $oldName]));
        }
        return $keys;
    }

    public function findByPk(int $id): ?TestAutoincrementEntity
    {
        return $this->createEntity($this->_findByPkCached($id));
    }

    public function findOneByName(string $name): ?TestAutoincrementEntity
    {
        return $this->createEntity($this->_findOneCached(['name' => $name]));
    }

    public function delete(TestAutoincrementEntity|AbstractEntity &$entity): void
    {
        if ($entity->name === 'Exception') {
            throw new \Exception('Test Exception');
        }
        parent::delete($entity);
    }

    /**
     * @return TestAutoincrementEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createEntities($this->_findAllCached(
            new Where('name = :name', ['name' => $name])
        ));
    }

    /**
     * @return TestAutoincrementEntity[]
     */
    public function findRecent(int $limit, int $offset): array
    {
        return $this->createEntities($this->_findAll(
            orderBy: ['id' => 'DESC'],
            limit: $limit,
            offset: $offset,
        ));
    }

    public function countAllByName(string $name): int
    {
        return $this->_countByAllCached(new Where('name = :name', ['name' => $name]));
    }

    /**
     * @return TestAutoincrementEntity[]
     */
    public function findMulti(array $ids): array
    {
        return $this->createEntities($this->_findMultiCached($ids));
    }

    public function truncate(): void
    {
        $this->getConnection()->executeStatement("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}