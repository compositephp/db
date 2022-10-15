<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Entities;

use Composite\DB\Entity\Attributes\Table;
use Composite\DB\Entity\Traits\SoftDelete;

#[Table(db: 'sqlite', name: 'TestCompositeSoftDelete')]
class TestCompositeSdEntity extends TestCompositeEntity
{
    use SoftDelete;
}