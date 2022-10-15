<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\TestStand;

enum TestBackedIntEnum: int
{
    case FooInt = 123;
    case BarInt = 456;
}