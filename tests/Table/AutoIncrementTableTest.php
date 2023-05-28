<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
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
     * @param class-string<Entities\TestAutoincrementEntity|Entities\TestAutoincrementSdEntity> $class
     * @dataProvider crud_dataProvider
     */
    public function test_crud(AbstractTable&IAutoincrementTable $table, string $class): void
    {
        $table->truncate();
        $tableConfig = TableConfig::fromEntitySchema($class::schema());

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
        if ($tableConfig->hasSoftDelete()) {
            /** @var Entities\TestAutoincrementSdEntity $deletedEntity */
            $deletedEntity = $table->findByPk($entity->id);
            $this->assertTrue($deletedEntity->isDeleted());
        } else {
            $this->assertEntityNotExists($table, $entity->id, $entity->name);
        }

        $e1 = new $class($this->getUniqueName());
        $e2 = new $class($this->getUniqueName());

        [$e1, $e2] = $table->saveMany([$e1, $e2]);
        $this->assertEntityExists($table, $e1);
        $this->assertEntityExists($table, $e2);
        $this->assertTrue($table->deleteMany([$e1, $e2]));

        if ($tableConfig->hasSoftDelete()) {
            /** @var Entities\TestAutoincrementSdEntity $deletedEntity1 */
            $deletedEntity1 = $table->findByPk($e1->id);
            $this->assertTrue($deletedEntity1->isDeleted());

            /** @var Entities\TestAutoincrementSdEntity $deletedEntity2 */
            $deletedEntity2 = $table->findByPk($e2->id);
            $this->assertTrue($deletedEntity2->isDeleted());
        } else {
            $this->assertEntityNotExists($table, $e1->id, $e1->name);
            $this->assertEntityNotExists($table, $e2->id, $e2->name);
        }
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