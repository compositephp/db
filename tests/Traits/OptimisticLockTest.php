<?php declare(strict_types=1);

namespace Composite\DB\Tests\Traits;

use Composite\DB\ConnectionManager;
use Composite\DB\Exceptions\DbException;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Tables;

final class OptimisticLockTest extends \PHPUnit\Framework\TestCase
{
    public function test_trait(): void
    {
        //checking that problem exists
        $aiEntity1 = new Entities\TestAutoincrementEntity(name: 'John');
        $aiTable1 = new Tables\TestAutoincrementTable();
        $aiTable2 = new Tables\TestAutoincrementTable();

        $aiTable1->save($aiEntity1);

        $aiEntity2 = $aiTable2->findByPk($aiEntity1->id);

        $db = ConnectionManager::getConnection($aiTable1->getConnectionName());

        $db->beginTransaction();
        $aiEntity1->name = 'John1';
        $aiTable1->save($aiEntity1);

        $aiEntity2->name = 'John2';
        $aiTable2->save($aiEntity2);

        $this->assertTrue($db->commit());

        $aiEntity3 = $aiTable1->findByPk($aiEntity1->id);
        $this->assertEquals('John2', $aiEntity3->name);

        //Checking optimistic lock
        $olEntity1 = new Entities\TestOptimisticLockEntity(name: 'John');
        $olTable1 = new Tables\TestOptimisticLockTable();
        $olTable2 = new Tables\TestOptimisticLockTable();

        $olTable1->save($olEntity1);

        $olEntity2 = $olTable2->findByPk($olEntity1->id);

        $db->beginTransaction();
        $olEntity1->name = 'John1';
        $olTable1->save($olEntity1);

        $olEntity2->name = 'John2';

        $exceptionCaught = false;
        try {
            $olTable2->save($olEntity2);
        } catch (DbException) {
            $exceptionCaught = true;
        }
        $this->assertTrue($exceptionCaught);

        $this->assertTrue($db->rollBack());

        $olEntity3 = $olTable1->findByPk($olEntity1->id);
        $this->assertEquals(1, $olEntity3->getVersion());
        $this->assertEquals('John', $olEntity3->name);
    }
}