<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\Columns;

use Composite\DB\AbstractEntity;

final class ObjectColumnTest extends \PHPUnit\Framework\TestCase
{
    public function cast_dataProvider(): array
    {
        $object = new \stdClass();
        $object->foo = 'bar';
        $object->int = 123;
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
                'value' => '{}',
                'expected' => new \stdClass(),
            ],
            [
                'value' => '[]',
                'expected' => null,
            ],
            [
                'value' => '[1,2,3]',
                'expected' => null,
            ],
            [
                'value' => 'abc',
                'expected' => null,
            ],
            [
                'value' => json_encode($object),
                'expected' => $object,
            ],
            [
                'value' => $object,
                'expected' => $object,
            ],
        ];
    }

    /**
     * @dataProvider cast_dataProvider
     */
    public function test_cast(mixed $value, ?object $expected): void
    {
        $class = new class extends AbstractEntity {
            public function __construct(
                public ?\stdClass $column = null,
            ) {}
        };
        $entity = $class::fromArray(['column' => $value]);
        $this->assertSame(
            $expected ? \json_encode($expected) : $expected,
            $entity->column ? \json_encode($entity->column) : $entity->column
        );
    }

    public function uncast_dataProvider(): array
    {
        $object = new \stdClass();
        $object->foo = 'bar';
        $object->int = 123;

        return [
            [
                'value' => null,
                'expected' => null,
            ],
            [
                'value' => new \stdClass(),
                'expected' => '{}',
            ],
            [
                'value' => $object,
                'expected' => '{"foo":"bar","int":123}',
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
                public ?\stdClass $column,
            ) {}
        };
        $actual = $entity->toArray()['column'];
        $this->assertSame($expected, $actual);

        $newEntity = $entity::fromArray(['column' => $actual]);
        $newActual = $newEntity->toArray()['column'];
        $this->assertSame(
            $entity->column ? \json_encode($entity->column) : $entity->column,
            $newEntity->column ? \json_encode($newEntity->column) : $newEntity->column
        );
        $this->assertSame($expected, $newActual);
    }
}