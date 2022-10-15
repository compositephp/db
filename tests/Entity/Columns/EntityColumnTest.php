<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\Columns;

use Composite\DB\AbstractEntity;
use Composite\DB\Tests\Entity\TestStand\TestSubEntity;

final class EntityColumnTest extends \PHPUnit\Framework\TestCase
{
    public function cast_dataProvider(): array
    {
        $entity = new TestSubEntity(str: 'bar', number: PHP_INT_MAX);
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
                'value' => '[1,2,3]',
                'expected' => new TestSubEntity(),
            ],
            [
                'value' => 'abc',
                'expected' => null,
            ],
            [
                'value' => '{}',
                'expected' => new TestSubEntity(),
            ],
            [
                'value' => '[]',
                'expected' => new TestSubEntity(),
            ],
            [
                'value' => json_encode($entity->toArray()),
                'expected' => $entity,
            ],
            [
                'value' => $entity,
                'expected' => $entity,
            ],
        ];
    }

    /**
     * @dataProvider cast_dataProvider
     */
    public function test_cast(mixed $value, ?TestSubEntity $expected): void
    {
        $class = new class extends AbstractEntity {
            public function __construct(
                public ?TestSubEntity $column = null,
            ) {}
        };
        $entity = $class::fromArray(['column' => $value]);
        $this->assertSame($expected?->toArray(), $entity->column?->toArray());
    }

    public function uncast_dataProvider(): array
    {
        $str = 'bar';
        $entity = new TestSubEntity(str: $str, number: PHP_INT_MAX);
        return [
            [
                'value' => null,
                'expected' => null,
            ],
            [
                'value' => $entity,
                'expected' => '{"str":"' . $str . '","number":' . PHP_INT_MAX . '}',
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
                public ?TestSubEntity $column,
            ) {}
        };
        $actual = $entity->toArray()['column'];
        $this->assertSame($expected, $actual);

        $newEntity = $entity::fromArray(['column' => $actual]);
        $newActual = $newEntity->toArray()['column'];
        $this->assertSame($entity->column?->toArray(), $newEntity->column?->toArray());
        $this->assertSame($expected, $newActual);
    }
}