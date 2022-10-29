<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities;

use Composite\DB\Attributes\{PrimaryKey};
use Composite\DB\Attributes\Table;
use Composite\Entity\AbstractEntity;

#[Table(db: 'sqlite', name: 'TestUnique')]
class TestUniqueEntity extends AbstractEntity
{
    public function __construct(
        #[PrimaryKey]
        public readonly string $id,
        public string $name,
        public readonly \DateTimeImmutable $created_at = new \DateTimeImmutable(),
    ) {}
}