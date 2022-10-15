<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Tables;

use Composite\DB\Entity\Schema;
use Composite\DB\Tests\Table\TestStand\Entities\TestUniqueSdEntity;

class TestUniqueSdTable extends TestUniqueTable
{
    protected function getSchema(): Schema
    {
        return TestUniqueSdEntity::schema();
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
                `id` VARCHAR(255) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP NOT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT TestUniqueSd PRIMARY KEY (`id`, `deleted_at`)
            );
            "
        );
        return true;
    }
}