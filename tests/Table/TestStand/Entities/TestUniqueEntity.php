<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Entities;

use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Attributes\{Table, PrimaryKey};

#[Table(db: 'sqlite')]
class TestUniqueEntity extends AbstractEntity
{
    public function __construct(
        #[PrimaryKey]
        public readonly string $id,
        public string $name,
        public readonly \DateTimeImmutable $created_at = new \DateTimeImmutable(),
    ) {}
}