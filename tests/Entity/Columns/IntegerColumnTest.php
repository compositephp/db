<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\Columns;

use Composite\DB\AbstractEntity;

final class IntegerColumnTest extends \PHPUnit\Framework\TestCase
{
    public function cast_dataProvider(): array
    {
        return [
            [
                'value' => null,
                'expected' => null,
            ],
            [
                'value' => 0,
                'expected' => 0,
            ],
            [
                'value' => 1,
                'expected' => 1,
            ],
            [
                'value' => PHP_INT_MAX,
                'expected' => PHP_INT_MAX,
            ],
            [
                'value' => '123',
                'expected' => 123,
            ],
            [
                'value' => 9.0,
                'expected' => 9,
            ],
            [
                'value' => 9.99,
                'expected' => 9,
            ],
            [
                'value' => -123,
                'expected' => -123,
            ],
            [
                'value' => -123.123,
                'expected' => -123,
            ],
            [
                'value' => '-123',
                'expected' => -123,
            ],
            [
                'value' => '0',
                'expected' => 0,
            ],
            [
                'value' => '',
                'expected' => 0,
            ],
            [
                'value' => 'abc',
                'expected' => 0,
            ],
        ];
    }

    /**
     * @dataProvider cast_dataProvider
     */
    public function test_cast(mixed $value, ?int $expected): void
    {
        $class = new class extends AbstractEntity {
            public function __construct(
                public ?int $column = null,
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
                'value' => 123,
                'expected' => 123,
            ],
            [
                'value' => -123,
                'expected' => -123,
            ],
            [
                'value' => PHP_INT_MAX,
                'expected' => PHP_INT_MAX,
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
                public ?int $column,
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