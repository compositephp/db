<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractCachedTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestUniqueEntity;
use Composite\DB\Tests\TestStand\Interfaces\IUniqueTable;
use Composite\Entity\AbstractEntity;
use Ramsey\Uuid\UuidInterface;

class TestUniqueCachedTable extends AbstractCachedTable implements IUniqueTable
{
    public function __construct(\Psr\SimpleCache\CacheInterface $cache)
    {
        parent::__construct($cache);
        (new TestUniqueTable())->init();
    }

    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestUniqueEntity::schema());
    }

    protected function getFlushCacheKeys(TestUniqueEntity|AbstractEntity $entity): array
    {
        return [
            $this->getListCacheKey('name = :name', ['name' => $entity->name]),
            $this->getCountCacheKey('name = :name', ['name' => $entity->name]),
        ];
    }

    public function findByPk(UuidInterface $id): ?TestUniqueEntity
    {
        return $this->createEntity($this->_findByPk($id));
    }

    /**
     * @return TestUniqueEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createEntities($this->_findAllCached(
            'name = :name',
            ['name' => $name],
        ));
    }

    public function countAllByName(string $name): int
    {
        return $this->_countAllCached(
            'name = :name',
            ['name' => $name],
        );
    }

    public function truncate(): void
    {
        $this->getConnection()->executeStatement("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}