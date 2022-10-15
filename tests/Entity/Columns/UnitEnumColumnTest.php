<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\Columns;

use Composite\DB\AbstractEntity;

final class UnitEnumColumnTest extends \PHPUnit\Framework\TestCase
{
    public function cast_dataProvider(): array
    {
        return [
            [
                'value' => null,
                'expected' => null,
            ],
            [
                'value' => '',
                'expected' => null,
            ],
            [
                'value' => 'Foo',
                'expected' => \Composite\DB\Tests\Entity\TestStand\TestUnitEnum::Foo,
            ],
            [
                'value' => 'Bar',
                'expected' => \Composite\DB\Tests\Entity\TestStand\TestUnitEnum::Bar,
            ],
            [
                'value' => \Composite\DB\Tests\Entity\TestStand\TestUnitEnum::Foo,
                'expected' => \Composite\DB\Tests\Entity\TestStand\TestUnitEnum::Foo,
            ],
            [
                'value' => \Composite\DB\Tests\Entity\TestStand\TestUnitEnum::Bar,
                'expected' => \Composite\DB\Tests\Entity\TestStand\TestUnitEnum::Bar,
            ],
            [
                'value' => 'non-exist',
                'expected' => null,
            ],
        ];
    }

    /**
     * @dataProvider cast_dataProvider
     */
    public function test_cast(mixed $value, ?\Composite\DB\Tests\Entity\TestStand\TestUnitEnum $expected): void
    {
        $class = new class extends AbstractEntity {
            public function __construct(
                public ?\Composite\DB\Tests\Entity\TestStand\TestUnitEnum $column = null,
            ) {}
        };
        $entity = $class::fromArray(['column' => $value]);
        $this->assertSame($expected, $entity->column);
    }

    public function uncast_dataProvider(): array
    {
        return [
            [
                'value' => null,
                'expected' => null,
            ],
            [
                'value' => \Composite\DB\Tests\Entity\TestStand\TestUnitEnum::Foo,
                'expected' => 'Foo',
            ],
            [
                'value' => \Composite\DB\Tests\Entity\TestStand\TestUnitEnum::Bar,
                'expected' => 'Bar',
            ],
        ];
    }

    /**
     * @dataProvider uncast_dataProvider
     */
    public function test_uncast(mixed $value, mixed $expected): void
    {
        $entity = new class($value) extends AbstractEntity {
            public function __construct(
                public ?\Composite\DB\Tests\Entity\TestStand\TestUnitEnum $column,
            ) {}
        };
        $actual = $entity->toArray()['column'];
        $this->assertSame($expected, $actual);

        $newEntity = $entity::fromArray(['column' => $actual]);
        $newActual = $newEntity->toArray()['column'];
        $this->assertSame($entity->column, $newEntity->column);
        $this->assertSame($expected, $newActual);
    }
}