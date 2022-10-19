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

    public function test_transactionCommit(): void
    {
        $dbm = self::getDatabaseManager();
        $autoIncrementTable = new Tables\TestAutoincrementTable($dbm);
        $compositeTable = new Tables\TestCompositeTable($dbm);

        $saveTransaction = new CombinedTransaction();

        $e1 = new Entities\TestAutoincrementEntity(name: 'Foo');
        $saveTransaction->save($autoIncrementTable, $e1);

        $e2 = new Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000), message: 'Bar');
        $saveTransaction->save($compositeTable, $e2);

        $saveTransaction->commit();

        $this->assertNotNull($autoIncrementTable->findByPk($e1->id));
        $this->assertNotNull($compositeTable->findOne($e2->user_id, $e2->post_id));

        $deleteTransaction = new CombinedTransaction();
        $deleteTransaction->delete($autoIncrementTable, $e1);
        $deleteTransaction->delete($compositeTable, $e2);
        $deleteTransaction->commit();

        $this->assertNull($autoIncrementTable->findByPk($e1->id));
        $this->assertNull($compositeTable->findOne($e2->user_id, $e2->post_id));
    }

    public function test_transactionRollback(): void
    {
        $dbm = self::getDatabaseManager();
        $autoIncrementTable = new Tables\TestAutoincrementTable($dbm);
        $compositeTable = new Tables\TestCompositeTable($dbm);

        $transaction = new CombinedTransaction();

        $e1 = new Entities\TestAutoincrementEntity(name: 'Foo');
        $transaction->save($autoIncrementTable, $e1);

        $e2 = new Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000), message: 'Bar');
        $transaction->save($compositeTable, $e2);

        $transaction->rollback();

        $this->assertNull($autoIncrementTable->findByPk($e1->id));
        $this->assertNull($compositeTable->findOne($e2->user_id, $e2->post_id));
    }

    public function test_transactionException(): void
    {
        $dbm = self::getDatabaseManager();
        $autoIncrementTable = new Tables\TestAutoincrementTable($dbm);
        $compositeTable = new Tables\TestCompositeTable($dbm);

        $transaction = new CombinedTransaction();

        $e1 = new Entities\TestAutoincrementEntity(name: 'Foo');
        $transaction->save($autoIncrementTable, $e1);

        $e2 = new Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000), message: 'Exception');
        try {
            $transaction->save($compositeTable, $e2);
            $transaction->commit();
            $this->assertFalse(true, 'This line should not be reached');
        } catch (DbException) {}

        $this->assertNull($autoIncrementTable->findByPk($e1->id));
        $this->assertNull($compositeTable->findOne($e2->user_id, $e2->post_id));
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