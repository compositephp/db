<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities;

use Composite\DB\Attributes;
use Composite\Entity\AbstractEntity;

#[Attributes\Table(connection: 'sqlite', name: 'Strict')]
class TestStrictEntity extends AbstractEntity
{
    #[Attributes\PrimaryKey(autoIncrement: true)]
    public readonly int $id;
    
    public function __construct(
        public \DateTimeImmutable $dti1,
    ) {}
}