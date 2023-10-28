<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\Enums\TestBackedEnum;
use Composite\DB\Tests\TestStand\Entities\TestUniqueEntity;
use Composite\DB\Tests\TestStand\Interfaces\IUniqueTable;
use Composite\DB\Where;
use Composite\Entity\AbstractEntity;
use Ramsey\Uuid\UuidInterface;

class TestUniqueTable extends AbstractTable implements IUniqueTable
{
    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    public function save(AbstractEntity|TestUniqueEntity &$entity): void
    {
        if ($entity->name === 'Exception') {
            throw new \Exception('Test Exception');
        }
        parent::save($entity);
    }

    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestUniqueEntity::schema());
    }

    public function findByPk(UuidInterface $id): ?TestUniqueEntity
    {
        return $this->createEntity($this->_findByPk($id));
    }

    /**
     * @return TestUniqueEntity[]
     */
    public function findAllByName(string $name): array
    {
        return $this->createEntities($this->_findAll(['name' => $name, 'status' => TestBackedEnum::ACTIVE]));
    }

    public function countAllByName(string $name): int
    {
        return $this->_countAll(new Where('name = :name', ['name' => $name]));
    }

    public function init(): bool
    {
        $this->getConnection()->executeStatement(
            "
            CREATE TABLE IF NOT EXISTS {$this->getTableName()}
            (
                `id` VARCHAR(255) NOT NULL CONSTRAINT TestUnique_pk PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `status` VARCHAR(16) DEFAULT 'Active' NOT NULL,
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