<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\TestStand;

enum TestUnitEnum
{
    case Foo;
    case Bar;

    public static function getCycleMigrationValue(): array
    {
        return array_map(fn($enum) => $enum->name, self::cases());
    }
}