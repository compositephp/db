<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\TestStand;

use Composite\DB\AbstractEntity;

class TestSubEntity extends AbstractEntity
{
    public function __construct(
        public string $str = 'foo',
        public int $number = 123,
    ) {}
}