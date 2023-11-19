<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestAutoincrementSdEntity;
use Composite\DB\Where;

class TestAutoincrementSdTable extends TestAutoincrementTable
{
    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestAutoincrementSdEntity::schema());
    }

    public function findByPk(int $id): ?TestAutoincrementSdEntity
    {
        return $this->_findByPk($id);
    }

    public function findOneByName(string $name): ?TestAutoincrementSdEntity
    {
        return $this->_findOne(['name' => $name, 'deleted_at' => null]);
    }

    /**
     * @return TestAutoincrementSdEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->_findAll(new Where('name = :name', ['name' => $name]));
    }

    /**
     * @return TestAutoincrementSdEntity[]
     */
    public function findRecent(int $limit, int $offset): array
    {
        return $this->_findAll(
            where: ['deleted_at' => null],
            orderBy: 'id DESC',
            limit: $limit,
            offset: $offset,
        );
    }

    public function init(): bool
    {
        $this->getConnection()->executeStatement(
            "
            CREATE TABLE IF NOT EXISTS {$this->getTableName()}
            (
                `id` INTEGER NOT NULL CONSTRAINT TestAutoincrementSd_pk PRIMARY KEY AUTOINCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `is_test` INTEGER NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL
            );
            "
        );
        return true;
    }
}