<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities;

use Composite\DB\Attributes\{PrimaryKey};
use Composite\DB\Attributes\Table;
use Composite\DB\Traits\SoftDelete;

#[Table(connection: 'sqlite', name: 'TestAutoIncrementSoftDelete')]
class TestAutoincrementSdEntity extends TestAutoincrementEntity
{
    use SoftDelete;

    #[PrimaryKey(autoIncrement: true)]
    public readonly int $id;
}