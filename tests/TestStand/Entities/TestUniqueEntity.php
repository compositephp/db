<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities;

use Composite\DB\Attributes\{PrimaryKey};
use Composite\DB\Attributes\Table;
use Composite\Entity\AbstractEntity;
use Ramsey\Uuid\UuidInterface;

#[Table(connection: 'sqlite', name: 'TestUnique')]
class TestUniqueEntity extends AbstractEntity
{
    public function __construct(
        #[PrimaryKey]
        public readonly UuidInterface $id,
        public string $name,
        public Enums\TestBackedEnum $status = Enums\TestBackedEnum::ACTIVE,
        public readonly \DateTimeImmutable $created_at = new \DateTimeImmutable(),
    ) {}
}