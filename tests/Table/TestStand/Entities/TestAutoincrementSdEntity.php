<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Entities;

use Composite\DB\Entity\Attributes\{Table, PrimaryKey};
use Composite\DB\Entity\Traits\SoftDelete;

#[Table(db: 'sqlite', name: 'TestAutoIncrementSoftDelete')]
class TestAutoincrementSdEntity extends TestAutoincrementEntity
{
    use SoftDelete;

    #[PrimaryKey(autoIncrement: true)]
    public readonly int $id;
}