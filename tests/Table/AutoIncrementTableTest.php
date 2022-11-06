<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\Tests\TestStand\Tables;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Interfaces\IAutoincrementTable;

final class AutoIncrementTableTest extends BaseTableTest
{
    public static function setUpBeforeClass(): void
    {
        (new Tables\TestAutoincrementTable())->init();
        (new Tables\TestAutoincrementSdTable())->init();
    }

    public function crud_dataProvider(): array
    {
        return [
            [
                new Tables\TestAutoincrementTable(),
                Entities\TestAutoincrementEntity::class,
            ],
            [
                new Tables\TestAutoincrementSdTable(),
                Entities\TestAutoincrementSdEntity::class,
            ],
            [
                new Tables\TestAutoincrementCachedTable(self::getCache()),
                Entities\TestAutoincrementEntity::class,
            ],
            [
                new Tables\TestAutoincrementSdCachedTable(self::getCache()),
                Entities\TestAutoincrementSdEntity::class,
            ],
        ];
    }

    /**
     * @dataProvider crud_dataProvider
     */
    public function test_crud(IAutoincrementTable $table, string $class): void
    {
        $table->truncate();

        $entity = new $class(
            name: $this->getUniqueName(),
        );
        $this->assertEntityNotExists($table, PHP_INT_MAX, uniqid());

        $table->save($entity);
        $this->assertGreaterThan(0, $entity->id);
        $this->assertEntityExists($table, $entity);

        $newName = $entity->name . ' changed';
        $entity->name = $newName;
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $foundEntity = $table->findOneByName($newName);
        $this->assertNotEmpty($foundEntity);
        $this->assertEquals($newName, $foundEntity->name);

        $table->delete($entity);
        $this->assertEntityNotExists($table, $entity->id, $entity->name);
    }

    private function assertEntityExists(IAutoincrementTable $table, Entities\TestAutoincrementEntity $entity): void
    {
        $this->assertNotNull($table->findByPk($entity->id));
        $entityFound = array_filter(
            $table->findAllByName($entity->name),
            fn ($item) => $item->toArray() === $entity->toArray()
        );
        $this->assertCount(1, $entityFound);
        $this->assertEquals(1, $table->countAllByName($entity->name));
    }

    private function assertEntityNotExists(IAutoincrementTable $table, int $id, string $name): void
    {
        $this->assertNull($table->findByPk($id));
        $entityFound = array_filter(
            $table->findAllByName($name),
            fn ($item) => $item->id === $id
        );
        $this->assertCount(0, $entityFound);
        $this->assertEquals(0, $table->countAllByName($name));
    }
}