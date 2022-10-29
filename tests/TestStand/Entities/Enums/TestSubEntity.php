<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities\Enums;

use Composite\Entity\AbstractEntity;

class TestSubEntity extends AbstractEntity
{
    public function __construct(
        public string $str = 'foo',
        public int $number = 123,
    ) {}
}