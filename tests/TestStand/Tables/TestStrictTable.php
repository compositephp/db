<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestStrictEntity;

class TestStrictTable extends AbstractTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestStrictEntity::schema());
    }

    public function buildEntity(array $data): ?TestStrictEntity
    {
        return $this->createEntity($data);
    }

    /**
     * @return TestStrictEntity[]
     */
    public function buildEntities(mixed $data): array
    {
        return $this->createEntities($data);
    }
}