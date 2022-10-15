<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Entities;

use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Attributes\{Table, PrimaryKey};

#[Table(db: 'sqlite')]
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