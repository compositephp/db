<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\CombinedTransaction;
use Composite\DB\Exceptions\DbException;
use Composite\DB\Tests\Table\TestStand\Entities;
use Composite\DB\Tests\Table\TestStand\Tables;

final class CombinedTransactionTest extends BaseTableTest
{

    public function test_transactionCommit(): void
    {
        $dbm = self::getDatabaseManager();
        $autoIncrementTable = new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementTable($dbm);
        $compositeTable = new \Composite\DB\Tests\TestStand\Tables\TestCompositeTable($dbm);

        $saveTransaction = new CombinedTransaction();

        $e1 = new \Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity(name: 'Foo');
        $saveTransaction->save($autoIncrementTable, $e1);

        $e2 = new \Composite\DB\Tests\TestStand\Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000), message: 'Bar');
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
        $autoIncrementTable = new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementTable($dbm);
        $compositeTable = new \Composite\DB\Tests\TestStand\Tables\TestCompositeTable($dbm);

        $transaction = new CombinedTransaction();

        $e1 = new \Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity(name: 'Foo');
        $transaction->save($autoIncrementTable, $e1);

        $e2 = new \Composite\DB\Tests\TestStand\Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000), message: 'Bar');
        $transaction->save($compositeTable, $e2);

        $transaction->rollback();

        $this->assertNull($autoIncrementTable->findByPk($e1->id));
        $this->assertNull($compositeTable->findOne($e2->user_id, $e2->post_id));
    }

    public function test_transactionException(): void
    {
        $dbm = self::getDatabaseManager();
        $autoIncrementTable = new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementTable($dbm);
        $compositeTable = new \Composite\DB\Tests\TestStand\Tables\TestCompositeTable($dbm);

        $transaction = new CombinedTransaction();

        $e1 = new \Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity(name: 'Foo');
        $transaction->save($autoIncrementTable, $e1);

        $e2 = new \Composite\DB\Tests\TestStand\Entities\TestCompositeEntity(user_id: $e1->id, post_id: mt_rand(1, 1000), message: 'Exception');
        try {
            $transaction->save($compositeTable, $e2);
            $transaction->commit();
            $this->assertFalse(true, 'This line should not be reached');
        } catch (DbException) {}

        $this->assertNull($autoIncrementTable->findByPk($e1->id));
        $this->assertNull($compositeTable->findOne($e2->user_id, $e2->post_id));
    }

    public function test_lock(): void
    {
        $dbm = self::getDatabaseManager();
        $cache = self::getCache();
        $table = new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementTable($dbm);
        $e1 = new \Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity(name: 'Foo');
        $e2 = new \Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity(name: 'Bar');

        $keyParts = [$table->getTableName(), 'insert_test', (string)microtime(true)];
        $transaction1 = new CombinedTransaction();
        $transaction1->lock($cache, $keyParts);

        $transaction2 = new CombinedTransaction();
        try {
            $transaction2->lock($cache, $keyParts);
            $this->assertFalse(false, 'Lock should not be free');
        } catch (DbException) {}

        $transaction1->save($table, $e1);
        $transaction1->commit();

        $transaction2->lock($cache, $keyParts);
        $transaction2->save($table, $e2);
        $transaction2->commit();

        $this->assertNotEmpty($table->findByPk($e1->id));
        $this->assertNotEmpty($table->findByPk($e2->id));
    }
}