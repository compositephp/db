<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities;

use Composite\DB\Attributes\{PrimaryKey};
use Composite\DB\Attributes\Table;
use Composite\DB\Traits;
use Composite\Entity\AbstractEntity;

#[Table(connection: 'sqlite', name: 'TestOptimisticLock')]
class TestOptimisticLockEntity extends AbstractEntity
{
    use Traits\OptimisticLock;

    #[PrimaryKey(autoIncrement: true)]
    public readonly int $id;

    public function __construct(
        public string $name,
        public readonly \DateTimeImmutable $created_at = new \DateTimeImmutable(),
    ) {}
}