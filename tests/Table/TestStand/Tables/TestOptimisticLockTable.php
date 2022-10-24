<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\Entity\Schema;
use Composite\DB\Tests\Table\TestStand\Entities\TestOptimisticLockEntity;

class TestOptimisticLockTable extends AbstractTable
{
    protected function getSchema(): Schema
    {
        return TestOptimisticLockEntity::schema();
    }

    public function findByPk(int $id): ?TestOptimisticLockEntity
    {
        return $this->createEntity($this->findByPkInternal($id));
    }

    public function init(): bool
    {
        $this->db->execute(
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