<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Entities;

use Composite\DB\Entity\Attributes\{Table, PrimaryKey};

#[Table(db: 'sqlite')]
class TestAutoincrementEntity extends \Composite\DB\AbstractEntity
{
    #[PrimaryKey(autoIncrement: true)]
    public readonly int $id;

    public function __construct(
        public string $name,
        public readonly \DateTimeImmutable $created_at = new \DateTimeImmutable(),
    ) {}
}