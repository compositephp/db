<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestUniqueEntity;
use Composite\DB\Tests\TestStand\Interfaces\IUniqueTable;

class TestUniqueTable extends AbstractTable implements IUniqueTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestUniqueEntity::schema());
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
        return $this->createEntities($this->findAllInternal(
            'name = :name',
            ['name' => $name],
        ));
    }

    public function countAllByName(string $name): int
    {
        return $this->countAllInternal(
            'name = :name',
            ['name' => $name],
        );
    }

    public function init(): bool
    {
        $this->getConnection()->executeStatement(
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
        $this->getConnection()->executeStatement("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}