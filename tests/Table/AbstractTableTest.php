<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractTable;
use Composite\DB\ConnectionManager;
use Composite\DB\Exceptions\DbException;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Tables;
use Composite\Entity\AbstractEntity;
use Composite\Entity\Exceptions\EntityException;

final class AbstractTableTest extends BaseTableTest
{
    public function getPkCondition_dataProvider(): array
    {
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
                new Entities\TestUniqueEntity(id: '123abc', name: 'John'),
                ['id' => '123abc'],
            ],
            [
                new Tables\TestUniqueTable(),
                '123abc',
                ['id' => '123abc'],
            ],
            [
                new Tables\TestAutoincrementSdTable(),
                Entities\TestAutoincrementSdEntity::fromArray(['id' => 123, 'name' => 'John']),
                ['id' => 123],
            ],
            [
                new Tables\TestCompositeSdTable(),
                new Entities\TestCompositeSdEntity(user_id: 123, post_id: 456, message: 'Text'),
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestUniqueSdTable(),
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

    public function test_illegalEntitySave(): void
    {
        $entity = new Entities\TestAutoincrementEntity(name: 'Foo');
        $compositeTable = new Tables\TestUniqueTable();

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

        $olTable1->init();

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
        $this->assertEquals(1, $olEntity3->version);
        $this->assertEquals('John', $olEntity3->name);
    }
}