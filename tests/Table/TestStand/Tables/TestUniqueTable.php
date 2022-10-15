<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\Entity\Schema;
use Composite\DB\Tests\Table\TestStand\Entities\TestUniqueEntity;
use Composite\DB\Tests\Table\TestStand\Interfaces\IUniqueTable;

class TestUniqueTable extends AbstractTable implements IUniqueTable
{
    protected function getSchema(): Schema
    {
        return TestUniqueEntity::schema();
    }

    public function findByPk(string $id): ?TestUniqueEntity
    {
        return $this->createEntity($this->findByPkInternal($id));
    }

    /**
     * @return TestUniqueEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createEntities($this->findAllInternal([
            'name' => $name,
        ]));
    }

    public function countAllByName(string $name): int
    {
        return $this->countAllInternal(['name' => $name]);
    }

    public function init(): bool
    {
        $this->db->execute(
            "
            CREATE TABLE IF NOT EXISTS {$this->getTableName()}
            (
                `id` VARCHAR(255) NOT NULL CONSTRAINT TestUnique_pk PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP NOT NULL
            );
            "
        );
        return true;
    }

    public function truncate(): void
    {
        $this->db->execute("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}