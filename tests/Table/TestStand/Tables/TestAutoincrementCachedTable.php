<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Tables;

use Composite\DB\AbstractCachedTable;
use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Schema;
use Composite\DB\Tests\Table\TestStand\Entities\TestAutoincrementEntity;
use Composite\DB\Tests\Table\TestStand\Interfaces\IAutoincrementTable;

class TestAutoincrementCachedTable extends AbstractCachedTable implements IAutoincrementTable
{
    protected function getSchema(): Schema
    {
        return TestAutoincrementEntity::schema();
    }

    protected function getFlushCacheKeys(TestAutoincrementEntity|AbstractEntity $entity): array
    {
        $keys = [
            $this->getOneCacheKey(['name' => $entity->name]),
            $this->getListCacheKey(['name' => $entity->name]),
            $this->getCountCacheKey(['name' => $entity->name]),
        ];
        $oldName = $entity->getOldValue('name');
        if (!$entity->isNew() && $oldName !== $entity->name) {
            $keys[] = $this->getOneCacheKey(['name' => $oldName]);
            $keys[] = $this->getListCacheKey(['name' => $oldName]);
            $keys[] = $this->getCountCacheKey(['name' => $oldName]);
        }
        return $keys;
    }

    public function findByPk(int $id): ?TestAutoincrementEntity
    {
        return $this->createEntity($this->findByPkCachedInternal($id));
    }

    public function findOneByName(string $name): ?TestAutoincrementEntity
    {
        return $this->createEntity($this->findOneCachedInternal(['name' => $name]));
    }

    /**
     * @return TestAutoincrementEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createEntities($this->findAllCachedInternal([
            'name' => $name,
        ]));
    }

    public function countAllByName(string $name): int
    {
        return $this->countAllCachedInternal(['name' => $name]);
    }

    public function truncate(): void
    {
        $this->db->execute("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}