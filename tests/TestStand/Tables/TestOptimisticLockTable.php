<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestOptimisticLockEntity;

class TestOptimisticLockTable extends AbstractTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestOptimisticLockEntity::schema());
    }

    public function findByPk(int $id): ?TestOptimisticLockEntity
    {
        return $this->createEntity($this->findByPkInternal($id));
    }

    public function init(): bool
    {
        $this->getConnection()->executeStatement(
            "
            CREATE TABLE IF NOT EXISTS {$this->getTableName()}
            (
                `id` INTEGER NOT NULL CONSTRAINT TestAutoincrement_pk PRIMARY KEY AUTOINCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `version` INTEGER NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
            );
            "
        );
        return true;
    }
}