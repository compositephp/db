<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Tables;

use Composite\DB\Entity\Schema;
use Composite\DB\Tests\Table\TestStand\Entities\TestAutoincrementSdEntity;

class TestAutoincrementSdTable extends TestAutoincrementTable
{
    protected function getSchema(): Schema
    {
        return TestAutoincrementSdEntity::schema();
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
        return $this->createEntities($this->findAllInternal([
            'name' => $name,
        ]));
    }

    public function init(): bool
    {
        $this->db->execute(
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