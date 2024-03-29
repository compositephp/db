<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities;

use Composite\DB\Attributes\{PrimaryKey};
use Composite\DB\Attributes\Table;

#[Table(connection: 'sqlite', name: 'TestAutoincrement')]
class TestAutoincrementEntity extends \Composite\Entity\AbstractEntity
{
    #[PrimaryKey(autoIncrement: true)]
    public readonly int $id;

    public function __construct(
        public string $name,
        public bool $is_test = false,
        public readonly \DateTimeImmutable $created_at = new \DateTimeImmutable(),
    ) {}
}