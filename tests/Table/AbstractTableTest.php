<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractEntity;
use Composite\DB\AbstractTable;
use Composite\DB\CombinedTransaction;
use Composite\DB\Exceptions\DbException;
use Composite\DB\Exceptions\EntityException;
use Composite\DB\Tests\Table\TestStand\Tables;
use Composite\DB\Tests\Table\TestStand\Entities;

final class AbstractTableTest extends BaseTableTest
{
    public function getPkCondition_dataProvider(): array
    {
        $dbm = self::getDatabaseManager();
        return [
            [
                new Tables\TestAutoincrementTable($dbm),
                Entities\TestAutoincrementEntity::fromArray(['id' => 123, 'name' => 'John']),
                ['id' => 123],
            ],
            [
                new Tables\TestAutoincrementTable($dbm),
                456,
                ['id' => 456],
            ],
            [
                new Tables\TestCompositeTable($dbm),
                new Entities\TestCompositeEntity(user_id: 123, post_id: 456, message: 'Text'),
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestCompositeTable($dbm),
                ['user_id' => 123, 'post_id' => 456],
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestUniqueTable($dbm),
                new Entities\TestUniqueEntity(id: '123abc', name: 'John'),
                ['id' => '123abc'],
            ],
            [
                new Tables\TestUniqueTable($dbm),
                '123abc',
                ['id' => '123abc'],
            ],
            [
                new Tables\TestAutoincrementSdTable($dbm),
                Entities\TestAutoincrementSdEntity::fromArray(['id' => 123, 'name' => 'John']),
                ['id' => 123],
            ],
            [
                new Tables\TestCompositeSdTable($dbm),
                new Entities\TestCompositeSdEntity(user_id: 123, post_id: 456, message: 'Text'),
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestUniqueSdTable($dbm),
                new Entities\TestUniqueSdEntity(id: '123abc', name: 'John'),
                ['id' => '123abc'],
            ],
        ];
    }

    /**
     * @dataProvider getPkCondition_dataProvider
     */
    public function test_getPkCondition(AbstractTable $table, int|string|array|AbstractEntity $object, array $expected): void
    {
        $reflectionMethod = new \ReflectionMethod($table, 'getPkCondition');
        $actual = $reflectionMethod->invoke($table, $object);
        $this->assertEquals($expected, $actual);
    }

    public function enrichCondition_dataProvider(): array
    {
        return [
            [
                new Tables\TestAutoincrementTable(self::getDatabaseManager()),
                ['id' => 123],
                ['id' => 123],
            ],
            [
                new Tables\TestCompositeTable(self::getDatabaseManager()),
                ['user_id' => 123, 'post_id' => 456],
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestUniqueTable(self::getDatabaseManager()),
                ['id' => '123abc'],
                ['id' => '123abc'],
            ],
            [
                new Tables\TestAutoincrementSdTable(self::getDatabaseManager()),
                ['id' => 123],
                ['id' => 123, 'deleted_at' => null],
            ],
            [
                new Tables\TestCompositeSdTable(self::getDatabaseManager()),
                ['user_id' => 123, 'post_id' => 456],
                ['user_id' => 123, 'post_id' => 456, 'deleted_at' => null],
            ],
            [
                new Tables\TestUniqueSdTable(self::getDatabaseManager()),
                ['id' => '123abc'],
                ['id' => '123abc', 'deleted_at' => null],
            ],
        ];
    }

    /**
     * @dataProvider enrichCondition_dataProvider
     */
    public function test_enrichCondition(AbstractTable $table, array $condition, array $expected): void
    {
        $reflectionMethod = new \ReflectionMethod($table, 'enrichCondition');
        $reflectionMethod->invokeArgs($table, [&$condition]);
        $this->assertEquals($expected, $condition);
    }

    public function test_illegalEntitySave(): void
    {
        $dbm = self::getDatabaseManager();
        $entity = new Entities\TestAutoincrementEntity(name: 'Foo');
        $compositeTable = new Tables\TestUniqueTable($dbm);

        $exceptionCatch = false;
        try {
            $compositeTable->save($entity);
        } catch (EntityException $e) {
            $exceptionCatch = true;
        }
        $this->assertTrue($exceptionCatch);
    }
}