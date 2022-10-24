<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Entities;

use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Attributes\{Table, PrimaryKey};
use Composite\DB\Entity\Traits;

#[Table(db: 'sqlite')]
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