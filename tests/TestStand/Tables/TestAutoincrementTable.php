<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity;
use Composite\DB\Tests\TestStand\Interfaces\IAutoincrementTable;

class TestAutoincrementTable extends AbstractTable implements IAutoincrementTable
{
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
                `id` INTEGER NOT NULL CONSTRAINT TestAutoincrement_pk PRIMARY KEY AUTOINCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
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