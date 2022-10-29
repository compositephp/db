<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities\Enums;

enum TestUnitEnum
{
    case Foo;
    case Bar;

    public static function getCycleMigrationValue(): array
    {
        return array_map(fn($enum) => $enum->name, self::cases());
    }
}