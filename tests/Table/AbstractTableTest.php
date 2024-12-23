<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractTable;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Tables;
use Composite\Entity\AbstractEntity;
use Composite\Entity\Exceptions\EntityException;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class AbstractTableTest extends \PHPUnit\Framework\TestCase
{
    public static function getPkCondition_dataProvider(): array
    {
        $uuid = Uuid::uuid4();
        return [
            [
                new Tables\TestAutoincrementTable(),
                Entities\TestAutoincrementEntity::fromArray(['id' => 123, 'name' => 'John']),
                ['id' => 123],
            ],
            [
                new Tables\TestAutoincrementTable(),
                456,
                ['id' => 456],
            ],
            [
                new Tables\TestCompositeTable(),
                new Entities\TestCompositeEntity(user_id: 123, post_id: 456, message: 'Text'),
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestCompositeTable(),
                ['user_id' => 123, 'post_id' => 456],
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestUniqueTable(),
                new Entities\TestUniqueEntity(id: $uuid, name: 'John'),
                ['id' => $uuid->toString()],
            ],
            [
                new Tables\TestUniqueTable(),
                $uuid,
                ['id' => $uuid->toString()],
            ],
            [
                new Tables\TestAutoincrementSdTable(),
                Entities\TestAutoincrementSdEntity::fromArray(['id' => 123, 'name' => 'John']),
                ['id' => 123],
            ],
        ];
    }

    #[DataProvider('getPkCondition_dataProvider')]
    public function test_getPkCondition(AbstractTable $table, int|string|array|AbstractEntity|UuidInterface $object, array $expected): void
    {
        $reflectionMethod = new \ReflectionMethod($table, 'getPkCondition');
        $actual = $reflectionMethod->invoke($table, $object);
        $this->assertEquals($expected, $actual);
    }

    public function test_illegalEntitySave(): void
    {
        $entity = new Entities\TestAutoincrementEntity(name: 'Foo');
        $compositeTable = new Tables\TestUniqueTable();

        $this->expectException(EntityException::class);
        $compositeTable->save($entity);
    }

    public function test_illegalCreateEntity(): void
    {
        $table = new Tables\TestStrictTable();
        $null = $table->buildEntity(['dti1' => 'abc']);
        $this->assertNull($null);

        $empty = $table->buildEntities([['dti1' => 'abc']]);
        $this->assertEmpty($empty);

        $empty = $table->buildEntities([]);
        $this->assertEmpty($empty);

        $empty = $table->buildEntities(false);
        $this->assertEmpty($empty);

        $empty = $table->buildEntities('abc');
        $this->assertEmpty($empty);

        $empty = $table->buildEntities(['abc']);
        $this->assertEmpty($empty);
    }

    #[DataProvider('buildWhere_dataProvider')]
    public function test_buildWhere($where, $expectedSQL, $expectedParams): void
    {
        $table = new Tables\TestStrictTable();

        $selectReflection = new \ReflectionMethod($table, 'select');
        $selectReflection->setAccessible(true);

        $queryBuilder = $selectReflection->invoke($table);

        $buildWhereReflection = new \ReflectionMethod($table, 'buildWhere');
        $buildWhereReflection->setAccessible(true);

        $buildWhereReflection->invokeArgs($table, [$queryBuilder, $where]);

        $this->assertEquals($expectedSQL, $queryBuilder->getSQL());
        $this->assertEquals($expectedParams, $queryBuilder->getParameters());
    }

    public static function buildWhere_dataProvider(): array
    {
        return [
            // Scalar value
            [
                ['column' => 1],
                'SELECT * FROM Strict WHERE column = :column',
                ['column' => 1]
            ],

            // Null value
            [
                ['column' => null],
                'SELECT * FROM Strict WHERE column IS NULL',
                []
            ],

            // Greater than comparison
            [
                ['column' => ['>', 0]],
                'SELECT * FROM Strict WHERE column > :column',
                ['column' => 0]
            ],

            // Less than comparison
            [
                ['column' => ['<', 5]],
                'SELECT * FROM Strict WHERE column < :column',
                ['column' => 5]
            ],

            // Greater than or equal to comparison
            [
                ['column' => ['>=', 3]],
                'SELECT * FROM Strict WHERE column >= :column',
                ['column' => 3]
            ],

            // Less than or equal to comparison
            [
                ['column' => ['<=', 7]],
                'SELECT * FROM Strict WHERE column <= :column',
                ['column' => 7]
            ],

            // Not equal to comparison with scalar value
            [
                ['column' => ['<>', 10]],
                'SELECT * FROM Strict WHERE column <> :column',
                ['column' => 10]
            ],

            // Not equal to comparison with null
            [
                ['column' => ['!=', null]],
                'SELECT * FROM Strict WHERE column IS NOT NULL',
                []
            ],

            // IN condition
            [
                ['column' => [1, 2, 3]],
                'SELECT * FROM Strict WHERE column IN(:column0, :column1, :column2)',
                ['column0' => 1, 'column1' => 2, 'column2' => 3]
            ],

            // Multiple conditions
            [
                ['column1' => 1, 'column2' => null, 'column3' => ['>', 5]],
                'SELECT * FROM Strict WHERE (column1 = :column1) AND (column2 IS NULL) AND (column3 > :column3)',
                ['column1' => 1, 'column3' => 5]
            ]
        ];
    }

    public function test_databaseSpecific(): void
    {
        $mySQLTable = new Tables\TestMySQLTable();
        $this->assertEquals('`column`', $mySQLTable->escapeIdentifierPub('column'));
        $this->assertEquals('`Database`.`Table`', $mySQLTable->escapeIdentifierPub('Database.Table'));

        $postgresTable = new Tables\TestPostgresTable();
        $this->assertEquals('"column"', $postgresTable->escapeIdentifierPub('column'));
    }
}