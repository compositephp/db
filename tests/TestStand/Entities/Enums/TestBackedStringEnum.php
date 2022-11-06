<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities\Enums;

enum TestBackedStringEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}