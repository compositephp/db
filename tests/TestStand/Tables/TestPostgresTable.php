<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity;

class TestPostgresTable extends AbstractTable
{
    protected function getConfig(): TableConfig
    {
        return new TableConfig(
            connectionName: 'postgres',
            tableName: 'Fake',
            entityClass: TestAutoincrementEntity::class,
            primaryKeys: [],
        );
    }

    public function escapeIdentifierPub(string $key): string
    {
        return $this->escapeIdentifier($key);
    }
}