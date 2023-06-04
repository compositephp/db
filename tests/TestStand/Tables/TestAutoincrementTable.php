<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity;
use Composite\DB\Tests\TestStand\Interfaces\IAutoincrementTable;

class TestAutoincrementTable extends AbstractTable implements IAutoincrementTable
{
    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestAutoincrementEntity::schema());
    }

    public function findByPk(int $id): ?TestAutoincrementEntity
    {
        return $this->createEntity($this->findByPkInternal($id));
    }

    public function findOneByName(string $name): ?TestAutoincrementEntity
    {
        return $this->createEntity($this->findOneInternal(['name' => $name]));
    }

    /**
     * @return TestAutoincrementEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createEntities($this->findAllInternal(
            whereString: 'name = :name',
            whereParams: ['name' => $name],
            orderBy: 'id',
        ));
    }

    /**
     * @return TestAutoincrementEntity[]
     */
    public function findRecent(int $limit, int $offset): array
    {
        return $this->createEntities($this->findAllInternal(
            orderBy: ['id' => 'DESC'],
            limit: $limit,
            offset: $offset,
        ));
    }

    public function countAllByName(string $name): int
    {
        return $this->countAllInternal(
            'name = :name',
            ['name' => $name]
        );
    }

    /**
     * @param int[] $ids
     * @return TestAutoincrementEntity[]
     * @throws \Composite\DB\Exceptions\DbException
     */
    public function findMulti(array $ids): array
    {
        return $this->createEntities($this->findMultiInternal($ids), 'id');
    }

    public function init(): bool
    {
        $this->getConnection()->executeStatement(
            "
            CREATE TABLE IF NOT EXISTS {$this->getTableName()}
            (
                `id` INTEGER NOT NULL CONSTRAINT TestAutoincrement_pk PRIMARY KEY AUTOINCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `is_test` INTEGER NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
            );
            "
        );
        return true;
    }

    public function truncate(): void
    {
        $this->getConnection()->executeStatement("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}