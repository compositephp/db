<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity;
use Composite\DB\Tests\TestStand\Interfaces\IAutoincrementTable;
use Composite\DB\Where;
use Composite\Entity\AbstractEntity;

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
        return $this->_findByPk($id);
    }

    public function findOneByName(string $name): ?TestAutoincrementEntity
    {
        return $this->_findOne(['name' => $name]);
    }

    public function delete(AbstractEntity|TestAutoincrementEntity $entity): void
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
        return $this->_findAll(
            where: new Where('name = :name', ['name' => $name]),
            orderBy: 'id',
        );
    }

    /**
     * @return TestAutoincrementEntity[]
     */
    public function findRecent(int $limit, int $offset): array
    {
        return $this->_findAll(
            orderBy: ['id' => 'DESC'],
            limit: $limit,
            offset: $offset,
        );
    }

    public function countAllByName(string $name): int
    {
        return $this->_countAll(new Where('name = :name', ['name' => $name]));
    }

    /**
     * @param int[] $ids
     * @return TestAutoincrementEntity[]
     * @throws \Composite\DB\Exceptions\DbException
     */
    public function findMulti(array $ids): array
    {
        return $this->_findMulti($ids, 'id');
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