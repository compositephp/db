<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities;

use Composite\DB\Attributes\Table;
use Composite\DB\Traits\SoftDelete;

#[Table(db: 'sqlite', name: 'TestCompositeSoftDelete')]
class TestCompositeSdEntity extends TestCompositeEntity
{
    use SoftDelete;
}