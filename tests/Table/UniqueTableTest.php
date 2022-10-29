<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\Tests\Table\TestStand\Entities;
use Composite\DB\Tests\Table\TestStand\Tables;
use Composite\DB\Tests\TestStand\Interfaces\IUniqueTable;

final class UniqueTableTest extends BaseTableTest
{
    public static function setUpBeforeClass(): void
    {
        (new \Composite\DB\Tests\TestStand\Tables\TestUniqueTable(self::getDatabaseManager()))->init();
        (new \Composite\DB\Tests\TestStand\Tables\TestUniqueSdTable(self::getDatabaseManager()))->init();
    }

    public function crud_dataProvider(): array
    {
        return [
            [
                new \Composite\DB\Tests\TestStand\Tables\TestUniqueTable(self::getDatabaseManager()),
                \Composite\DB\Tests\TestStand\Entities\TestUniqueEntity::class,
            ],
            [
                new \Composite\DB\Tests\TestStand\Tables\TestUniqueSdTable(self::getDatabaseManager()),
                \Composite\DB\Tests\TestStand\Entities\TestUniqueSdEntity::class,
            ],
            [
                new \Composite\DB\Tests\TestStand\Tables\TestUniqueCachedTable(self::getDatabaseManager(), self::getCache()),
                \Composite\DB\Tests\TestStand\Entities\TestUniqueEntity::class,
            ],
            [
                new \Composite\DB\Tests\TestStand\Tables\TestUniqueSdCachedTable(self::getDatabaseManager(), self::getCache()),
                \Composite\DB\Tests\TestStand\Entities\TestUniqueSdEntity::class,
            ],
        ];
    }

    /**
     * @dataProvider crud_dataProvider
     */
    public function test_crud(IUniqueTable $table, string $class): void
    {
        $table->truncate();

        $entity = new $class(
            id: uniqid(),
            name: $this->getUniqueName(),
        );
        $this->assertEntityNotExists($table, $entity);
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $entity->name = $entity->name . ' changed';
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $table->delete($entity);
        $this->assertEntityNotExists($table, $entity);

        $newEntity = new $entity(
            id: $entity->id,
            name: $entity->name . ' new',
        );
        $table->save($newEntity);
        $this->assertEntityExists($table, $newEntity);

        $table->delete($newEntity);
        $this->assertEntityNotExists($table, $newEntity);
    }

    private function assertEntityExists(IUniqueTable $table, \Composite\DB\Tests\TestStand\Entities\TestUniqueEntity $entity): void
    {
        $this->assertNotNull($table->findByPk($entity->id));
        $entityFound = array_filter(
            $table->findAllByName($entity->name),
            fn ($item) => $item->toArray() === $entity->toArray()
        );
        $this->assertCount(1, $entityFound);
        $this->assertEquals(1, $table->countAllByName($entity->name));
    }

    private function assertEntityNotExists(IUniqueTable $table, \Composite\DB\Tests\TestStand\Entities\TestUniqueEntity $entity): void
    {
        $this->assertNull($table->findByPk($entity->id));
        $entityFound = array_filter(
            $table->findAllByName($entity->name),
            fn ($item) => $item->toArray() === $entity->toArray()
        );
        $this->assertCount(0, $entityFound);
        $this->assertEquals(0, $table->countAllByName($entity->name));
    }
}