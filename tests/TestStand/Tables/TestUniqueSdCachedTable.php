<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractCachedTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestUniqueSdEntity;
use Composite\DB\Tests\TestStand\Interfaces\IUniqueTable;
use Composite\Entity\AbstractEntity;

class TestUniqueSdCachedTable extends AbstractCachedTable implements IUniqueTable
{
    public function __construct(\Psr\SimpleCache\CacheInterface $cache)
    {
        parent::__construct($cache);
        (new TestUniqueSdTable())->init();
    }

    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestUniqueSdEntity::schema());
    }

    protected function getFlushCacheKeys(TestUniqueSdEntity|AbstractEntity $entity): array
    {
        return [
            $this->getListCacheKey('name = :name', ['name' => $entity->name]),
            $this->getCountCacheKey('name = :name', ['name' => $entity->name]),
        ];
    }

    public function findByPk(string $id): ?TestUniqueSdEntity
    {
        return $this->createEntity($this->findByPkInternal($id));
    }

    /**
     * @return TestUniqueSdEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createEntities($this->findAllCachedInternal(
            'name = :name',
            ['name' => $name],
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