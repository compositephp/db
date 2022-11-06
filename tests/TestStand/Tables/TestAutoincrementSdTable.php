<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestAutoincrementSdEntity;

class TestAutoincrementSdTable extends TestAutoincrementTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestAutoincrementSdEntity::schema());
    }

    public function findByPk(int $id): ?TestAutoincrementSdEntity
    {
        return $this->createEntity($this->findByPkInternal($id));
    }

    public function findOneByName(string $name): ?TestAutoincrementSdEntity
    {
        return $this->createEntity($this->findOneInternal(['name' => $name]));
    }

    /**
     * @return TestAutoincrementSdEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createEntities($this->findAllInternal(
            'name = :name',
            ['name' => $name]
        ));
    }

    public function init(): bool
    {
        $this->getConnection()->executeStatement(
            "
            CREATE TABLE IF NOT EXISTS {$this->getTableName()}
            (
                `id` INTEGER NOT NULL CONSTRAINT TestAutoincrementSd_pk PRIMARY KEY AUTOINCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL
            );
            "
        );
        return true;
    }
}