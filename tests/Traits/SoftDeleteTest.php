<?php declare(strict_types=1);

namespace Composite\DB\Tests\Traits;

use Composite\Entity\AbstractEntity;
use Composite\DB\Traits\SoftDelete;

final class SoftDeleteTest extends \PHPUnit\Framework\TestCase
{
    public function test_trait(): void
    {
        $entity = new class extends AbstractEntity {
            use SoftDelete;

            public function __construct(
                public string $foo = 'bar',
            ) {}

            public function getDeletedAt(): ?\DateTimeImmutable
            {
                return $this->deleted_at;
            }
        };
        $dt = new \DateTimeImmutable();
        $entity->delete($dt);
        $this->assertTrue($entity->isDeleted());
        $this->assertSame($dt, $entity->getDeletedAt());
    }
}