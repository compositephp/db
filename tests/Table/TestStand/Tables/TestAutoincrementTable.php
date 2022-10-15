<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\Entity\Schema;
use Composite\DB\Tests\Table\TestStand\Entities\TestAutoincrementEntity;
use Composite\DB\Tests\Table\TestStand\Interfaces\IAutoincrementTable;

class TestAutoincrementTable extends AbstractTable implements IAutoincrementTable
{
    protected function getSchema(): Schema
    {
        return TestAutoincrementEntity::schema();
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

    public function findRandom(): ?TestAutoincrementEntity
    {
        return $this->createEntity(
            $this->select()->where('id', 1)->run()->fetch()
        );
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