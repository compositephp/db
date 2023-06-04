<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractCachedTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestAutoincrementSdEntity;
use Composite\DB\Tests\TestStand\Interfaces\IAutoincrementTable;
use Composite\Entity\AbstractEntity;

class TestAutoincrementSdCachedTable extends AbstractCachedTable implements IAutoincrementTable
{
    public function __construct(\Psr\SimpleCache\CacheInterface $cache)
    {
        parent::__construct($cache);
        (new TestAutoincrementSdTable)->init();
    }

    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestAutoincrementSdEntity::schema());
    }

    protected function getFlushCacheKeys(TestAutoincrementSdEntity|AbstractEntity $entity): array
    {
        $keys = [
            $this->getOneCacheKey(['name' => $entity->name]),
            $this->getListCacheKey('name = :name', ['name' => $entity->name]),
            $this->getCountCacheKey('name = :name', ['name' => $entity->name]),
        ];
        $oldName = $entity->getOldValue('name');
        if ($oldName !== null && $oldName !== $entity->name) {
            $keys[] = $this->getOneCacheKey(['name' => $oldName]);
            $keys[] = $this->getListCacheKey('name = :name', ['name' => $oldName]);
            $keys[] = $this->getCountCacheKey('name = :name', ['name' => $oldName]);
        }
        return $keys;
    }

    public function findByPk(int $id): ?TestAutoincrementSdEntity
    {
        return $this->createEntity($this->findByPkInternal($id));
    }

    public function findOneByName(string $name): ?TestAutoincrementSdEntity
    {
        return $this->createEntity($this->findOneCachedInternal(['name' => $name]));
    }

    /**
     * @return TestAutoincrementSdEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createEntities($this->findAllCachedInternal(
            'name = :name',
            ['name' => $name, 'deleted_at' => null],
        ));
    }

    /**
     * @return TestAutoincrementSdEntity[]
     */
    public function findRecent(int $limit, int $offset): array
    {
        return $this->createEntities($this->findAllInternal(
            orderBy: 'id DESC',
            limit: $limit,
            offset: $offset,
        ));
    }

    public function countAllByName(string $name): int
    {
        return $this->countAllCachedInternal(
            'name = :name',
            ['name' => $name],
        );
    }

    public function truncate(): void
    {
        $this->getConnection()->executeStatement("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}