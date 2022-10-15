<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\Columns;

use Composite\DB\AbstractEntity;
use Composite\DB\Tests\Entity\TestStand\TestBackedIntEnum;

final class BackedIntEnumColumnTest extends \PHPUnit\Framework\TestCase
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
                'value' => TestBackedIntEnum::FooInt->value,
                'expected' => TestBackedIntEnum::FooInt,
            ],
            [
                'value' => TestBackedIntEnum::BarInt->value,
                'expected' => TestBackedIntEnum::BarInt,
            ],
            [
                'value' => TestBackedIntEnum::FooInt,
                'expected' => TestBackedIntEnum::FooInt,
            ],
            [
                'value' => TestBackedIntEnum::BarInt,
                'expected' => TestBackedIntEnum::BarInt,
            ],
            [
                'value' => (string)TestBackedIntEnum::BarInt->value,
                'expected' => TestBackedIntEnum::BarInt,
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
    public function test_cast(mixed $value, ?TestBackedIntEnum $expected): void
    {
        $class = new class extends AbstractEntity {
            public function __construct(
                public ?TestBackedIntEnum $column = null,
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
                'value' => TestBackedIntEnum::FooInt,
                'expected' => TestBackedIntEnum::FooInt->value,
            ],
            [
                'value' => TestBackedIntEnum::BarInt,
                'expected' => TestBackedIntEnum::BarInt->value,
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
                public ?TestBackedIntEnum $column,
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