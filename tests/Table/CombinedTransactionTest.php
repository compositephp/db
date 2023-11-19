<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\CombinedTransaction;
use Composite\DB\Exceptions\DbException;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Tables;
use Composite\DB\Tests\Helpers;

final class CombinedTransactionTest extends \PHPUnit\Framework\TestCase
{
    public function test_transactionCommit(): void
    {
        $autoIncrementTable = new Tables\TestAutoincrementTable();
        $compositeTable = new Tables\TestCompositeTable();

        $saveTransaction = new CombinedTransaction();

        $e1 = new Entities\TestAutoincrementEntity(name: 'Foo');
        $saveTransaction->save($autoIncrementTable, $e1);

        $e2 = new Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000000), message: 'Bar');
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

    public function test_saveDeleteMany(): void
    {
        $autoIncrementTable = new Tables\TestAutoincrementTable();
        $compositeTable = new Tables\TestCompositeTable();

        $saveTransaction = new CombinedTransaction();

        $e1 = new Entities\TestAutoincrementEntity(name: 'Foo');
        $saveTransaction->save($autoIncrementTable, $e1);

        $e2 = new Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000000), message: 'Foo');
        $e3 = new Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000000), message: 'Bar');
        $saveTransaction->saveMany($compositeTable, [$e2, $e3]);

        $saveTransaction->commit();

        $this->assertNotNull($autoIncrementTable->findByPk($e1->id));
        $this->assertNotNull($compositeTable->findOne($e2->user_id, $e2->post_id));
        $this->assertNotNull($compositeTable->findOne($e3->user_id, $e3->post_id));

        $deleteTransaction = new CombinedTransaction();
        $deleteTransaction->delete($autoIncrementTable, $e1);
        $deleteTransaction->deleteMany($compositeTable, [$e2, $e3]);
        $deleteTransaction->commit();
    }

    public function test_transactionRollback(): void
    {
        $autoIncrementTable = new Tables\TestAutoincrementTable();
        $compositeTable = new Tables\TestCompositeTable();

        $transaction = new CombinedTransaction();

        $e1 = new Entities\TestAutoincrementEntity(name: 'Foo');
        $transaction->save($autoIncrementTable, $e1);

        $e2 = new Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000), message: 'Bar');
        $transaction->save($compositeTable, $e2);

        $transaction->rollback();

        $this->assertNull($autoIncrementTable->findByPk($e1->id));
        $this->assertNull($compositeTable->findOne($e2->user_id, $e2->post_id));
    }

    public function test_failedSave(): void
    {
        $autoIncrementTable = new Tables\TestAutoincrementTable();
        $compositeTable = new Tables\TestCompositeTable();

        $transaction = new CombinedTransaction();

        $e1 = new Entities\TestAutoincrementEntity(name: 'Foo');
        $transaction->save($autoIncrementTable, $e1);

        $e2 = new Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000), message: 'Exception');
        try {
            $transaction->save($compositeTable, $e2);
            $transaction->commit();
            $this->fail('This line should not be reached');
        } catch (DbException) {}

        $this->assertNull($autoIncrementTable->findByPk($e1->id));
        $this->assertNull($compositeTable->findOne($e2->user_id, $e2->post_id));
    }

    public function test_failedDelete(): void
    {
        $autoIncrementTable = new Tables\TestAutoincrementTable();
        $compositeTable = new Tables\TestCompositeTable();

        $aiEntity = new Entities\TestAutoincrementEntity(name: 'Foo');
        $cEntity = new Entities\TestCompositeEntity(user_id: mt_rand(1, 1000), post_id: mt_rand(1, 1000), message: 'Bar');;

        $autoIncrementTable->save($aiEntity);
        $compositeTable->save($cEntity);

        $transaction = new CombinedTransaction();
        try {
            $aiEntity->name = 'Foo1';
            $cEntity->message = 'Exception';

            $transaction->save($autoIncrementTable, $aiEntity);
            $transaction->delete($compositeTable, $cEntity);

            $transaction->commit();
            $this->fail('This line should not be reached');
        } catch (DbException) {}

        $this->assertEquals('Foo', $autoIncrementTable->findByPk($aiEntity->id)->name);
        $this->assertNotNull($compositeTable->findOne($cEntity->user_id, $cEntity->post_id));
    }

    public function test_lockFailed(): void
    {
        $cache = new Helpers\FalseCache();
        $keyParts = [uniqid()];
        $transaction = new CombinedTransaction();

        $this->expectException(DbException::class);
        $transaction->lock($cache, $keyParts);
    }

    public function test_lock(): void
    {
        $cache = Helpers\CacheHelper::getCache();
        $table = new Tables\TestAutoincrementTable();

        $e1 = new Entities\TestAutoincrementEntity(name: 'Foo');
        $e2 = new Entities\TestAutoincrementEntity(name: 'Bar');

        $keyParts = [$table->getTableName(), 'insert_test', (string)microtime(true)];
        $transaction1 = new CombinedTransaction();
        $transaction1->lock($cache, $keyParts);

        $transaction2 = new CombinedTransaction();
        try {
            $transaction2->lock($cache, $keyParts);
            $this->fail('Lock should not be free');
        } catch (DbException) {
            $this->assertTrue(true);
        }

        $transaction1->save($table, $e1);
        $transaction1->commit();

        $transaction2->lock($cache, $keyParts);
        $transaction2->save($table, $e2);
        $transaction2->commit();

        $this->assertNotEmpty($table->findByPk($e1->id));
        $this->assertNotEmpty($table->findByPk($e2->id));
    }

    /**
     * @dataProvider buildLockKey_dataProvider
     */
    public function test_buildLockKey($keyParts, $expectedResult)
    {
        $reflection = new \ReflectionClass(CombinedTransaction::class);
        $object = new CombinedTransaction();
        $result = $reflection->getMethod('buildLockKey')->invoke($object, $keyParts);
        $this->assertEquals($expectedResult, $result);
    }

    public static function buildLockKey_dataProvider()
    {
        return [
            'empty array' => [[], 'composite.lock'],
            'one element' => [['element'], 'composite.lock.element'],
            'exact length' => [[str_repeat('a', 49)], 'composite.lock.' . str_repeat('a', 49)],
            'more than max length' => [[str_repeat('a', 55)], sha1('composite.lock.' . str_repeat('a', 55))],
        ];
    }
}