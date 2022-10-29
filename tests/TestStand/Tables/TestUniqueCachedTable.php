<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractCachedTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestUniqueEntity;
use Composite\DB\Tests\TestStand\Interfaces\IUniqueTable;
use Composite\Entity\AbstractEntity;

class TestUniqueCachedTable extends AbstractCachedTable implements IUniqueTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestUniqueEntity::schema());
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