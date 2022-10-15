<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Tables;

use Composite\DB\AbstractCachedTable;
use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Schema;
use Composite\DB\Tests\Table\TestStand\Entities\TestUniqueEntity;
use Composite\DB\Tests\Table\TestStand\Interfaces\IUniqueTable;

class TestUniqueCachedTable extends AbstractCachedTable implements IUniqueTable
{
    protected function getSchema(): Schema
    {
        return TestUniqueEntity::schema();
    }

    protected function getFlushCacheKeys(TestUniqueEntity|AbstractEntity $entity): array
    {
        return [
            $this->getListCacheKey(['name' => $entity->name]),
            $this->getCountCacheKey(['name' => $entity->name]),
        ];
    }

    public function findByPk(string $id): ?TestUniqueEntity
    {
        return $this->createEntity($this->findByPkInternal($id));
    }

    /**
     * @return TestUniqueEntity[]
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