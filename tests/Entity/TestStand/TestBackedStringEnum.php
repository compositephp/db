<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\TestStand;

enum TestBackedStringEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';

    public static function getCycleMigrationValue(): array
    {
        return array_map(fn($enum) => $enum->value, self::cases());
    }
}