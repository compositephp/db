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
        } catch (EntityException) {
            $exceptionCatch = true;
        }
        $this->assertTrue($exceptionCatch);
    }

    public function test_optimisticLock(): void
    {
        $dbm = self::getDatabaseManager();

        //checking that problem exists
        $aiEntity1 = new Entities\TestAutoincrementEntity(name: 'John');
        $aiTable1 = new Tables\TestAutoincrementTable($dbm);
        $aiTable2 = new Tables\TestAutoincrementTable($dbm);

        $aiTable1->save($aiEntity1);

        $aiEntity2 = $aiTable2->findByPk($aiEntity1->id);

        $db1 = $aiTable1->getDb();
        $db1->begin();
        $aiEntity1->name = 'John1';
        $aiTable1->save($aiEntity1);

        $db2 = $aiTable2->getDb();
        $db2->begin();
        $aiEntity2->name = 'John2';
        $aiTable2->save($aiEntity2);

        $this->assertTrue($db2->commit());
        $this->assertTrue($db1->commit());

        $aiEntity3 = $aiTable1->findByPk($aiEntity1->id);
        $this->assertEquals('John2', $aiEntity3->name);
        
        //Checking optimistic lock
        $olEntity1 = new Entities\TestOptimisticLockEntity(name: 'John');
        $olTable1 = new Tables\TestOptimisticLockTable($dbm);
        $olTable2 = new Tables\TestOptimisticLockTable($dbm);

        $olTable1->init();

        $olTable1->save($olEntity1);

        $olEntity2 = $olTable2->findByPk($olEntity1->id);

        $db1 = $olTable1->getDb();
        $db1->begin();
        $olEntity1->name = 'John1';
        $olTable1->save($olEntity1);

        $db2 = $olTable2->getDb();
        $db2->begin();
        $olEntity2->name = 'John2';

        $exceptionCaught = false;
        try {
            $olTable2->save($olEntity2);
        } catch (DbException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $this->assertTrue($db2->commit());
        $this->assertTrue($db1->commit());

        $olEntity3 = $olTable1->findByPk($olEntity1->id);
        $this->assertEquals(2, $olEntity3->version);
        $this->assertEquals('John1', $olEntity3->name);
    }
}