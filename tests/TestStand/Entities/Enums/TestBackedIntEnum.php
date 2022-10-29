<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities\Enums;

enum TestBackedIntEnum: int
{
    case FooInt = 123;
    case BarInt = 456;
}