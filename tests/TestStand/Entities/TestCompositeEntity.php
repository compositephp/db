<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities;

use Composite\DB\Attributes\{PrimaryKey, Table};
use Composite\Entity\AbstractEntity;

#[Table(db: 'sqlite', name: 'TestComposite')]
class TestCompositeEntity extends AbstractEntity
{
    public function __construct(
        #[PrimaryKey]
        public readonly int $user_id,
        #[PrimaryKey]
        public readonly int $post_id,
        public string $message,
        public readonly \DateTimeImmutable $created_at = new \DateTimeImmutable(),
    ) {}
}