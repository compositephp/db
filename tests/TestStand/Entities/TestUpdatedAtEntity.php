<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities;

use Composite\DB\Traits\UpdatedAt;
use Composite\DB\Attributes\{PrimaryKey};
use Composite\DB\Attributes\Table;
use Composite\Entity\AbstractEntity;

#[Table(connection: 'sqlite', name: 'TestUpdatedAt')]
class TestUpdatedAtEntity extends AbstractEntity
{
    use UpdatedAt;
    #[PrimaryKey(autoIncrement: true)]
    public readonly string $id;

    public function __construct(
        public string $name,
        public readonly \DateTimeImmutable $created_at = new \DateTimeImmutable(),
    ) {}
}