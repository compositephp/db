<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity;

use Composite\DB\AbstractEntity;
use Composite\DB\Helpers\DateTimeHelper;

final class AbstractEntityTest extends \PHPUnit\Framework\TestCase
{
    public function hydration_dataProvider(): array
    {
        $object = new \stdClass();
        $object->foo = 'bar';

        $dateTime = new \DateTime('2000-01-01 00:00:00');
        $dateTimeImmutable = new \DateTimeImmutable();

        $subEntity = new TestStand\TestSubEntity(str: 'bar');
        $castableUnixTime = strtotime('2000-01-01 01:02:03');
        $castable = new TestStand\TestCastableIntObject($castableUnixTime);

        return [
            [
                'entity' => new class(
                    int1: 1,
                    int2: 2,
                    int3: 3,
                    int4: 4,
                    int5: 5,
                ) extends AbstractEntity {
                    protected int $int3;
                    protected int $int6 = 6;

                    public function __construct(
                        public int $int1,
                        protected int $int2,
                        int $int3,
                        private int $int4 = 555,
                        int $int5 = 999,
                    ) {
                        $this->int3 = $int3;
                    }
                },
                'expected' => [
                    'int3' => 3,
                    'int6' => 6,
                    'int1' => 1,
                    'int2' => 2,
                ]
            ],
            [
                'entity' => new TestStand\TestEntity(
                    arr: [1, 2, 3],
                    object: $object,
                    date_time: $dateTime,
                    date_time_immutable: $dateTimeImmutable,
                    entity: $subEntity,
                    castable: $castable,
                ),
                'expected' => [
                    'str' => 'foo',
                    'int' => 999,
                    'float' => 9.99,
                    'bool' => true,
                    'arr' => '[1,2,3]',
                    'object' => '{"foo":"bar"}',
                    'date_time' => DateTimeHelper::dateTimeToString($dateTime),
                    'date_time_immutable' => DateTimeHelper::dateTimeToString($dateTimeImmutable),
                    'backed_enum' => 'foo',
                    'unit_enum' => 'Bar',
                    'entity' => '{"str":"bar","number":123}',
                    'castable' => $castableUnixTime,
                ]
            ],
        ];
    }

    /**
     * @dataProvider hydration_dataProvider
     */
    public function test_hydration(AbstractEntity $entity, array $expected): void
    {
        $actual = $entity->toArray();
        $this->assertSame($expected, $actual);
        $cloneEntity = $entity::fromArray($actual);
        $this->assertSame($entity->toArray(), $cloneEntity->toArray());
    }

    public function changedColumns_dataProvider(): array
    {
        return [
            [
                [],
            ],
            [
                [
                    'int' => '123456',
                    'date_time' => '2020-01-01 00:00:01',
                ],
            ],
        ];
    }

    /**
     * @dataProvider changedColumns_dataProvider
     */
    public function test_changedColumns(array $createData): void
    {
        $entity = TestStand\TestEntity::fromArray($createData);
        $this->assertSame([], $entity->getChangedColumns());
        $entity->str = 'foo bar';
        $this->assertSame(['str' => 'foo bar'], $entity->getChangedColumns());
        $entity->resetChangedColumns();
        $this->assertSame([], $entity->getChangedColumns());
    }

    public function test_getOldValue(): void
    {
        $entity = new class(
            int1: 1,
            int2: 2,
        ) extends AbstractEntity {
            public function __construct(
                public int $int1,
                public ?int $int2,
                public int $int3 = 3,
            ) {}
        };
        $this->assertTrue($entity->isNew());

        $this->assertSame(
            [
                'int1' => 1,
                'int2' => 2,
                'int3' => 3,
            ],
            $entity->getChangedColumns()
        );
        $this->assertNull($entity->getOldValue('int1'));
        $this->assertNull($entity->getOldValue('int2'));
        $this->assertNull($entity->getOldValue('int3'));

        $entity->resetChangedColumns();

        $this->assertEquals(1, $entity->getOldValue('int1'));
        $this->assertEquals(2, $entity->getOldValue('int2'));
        $this->assertEquals(3, $entity->getOldValue('int3'));

        $this->assertFalse($entity->isNew());
        $this->assertSame([], $entity->getChangedColumns());

        $entity->int1 = 11;
        $this->assertSame(['int1' => 11], $entity->getChangedColumns());
        $this->assertEquals(1, $entity->getOldValue('int1'));

        $entity->int2 = null;
        $this->assertSame(
            [
                'int1' => 11,
                'int2' => null,
            ],
            $entity->getChangedColumns()
        );
        $this->assertEquals(2, $entity->getOldValue('int2'));

        $entity->resetChangedColumns();

        $this->assertEquals(11, $entity->getOldValue('int1'));
        $this->assertEquals(null, $entity->getOldValue('int2'));
        $this->assertEquals(3, $entity->getOldValue('int3'));

        $this->assertFalse($entity->isNew());
        $this->assertSame([], $entity->getChangedColumns());
    }
}