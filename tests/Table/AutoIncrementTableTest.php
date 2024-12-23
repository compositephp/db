<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractTable;
use Composite\DB\Exceptions\DbException;
use Composite\DB\TableConfig;
use Composite\DB\Tests\Helpers;
use Composite\DB\Tests\TestStand\Tables;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Interfaces\IAutoincrementTable;
use PHPUnit\Framework\Attributes\DataProvider;

final class AutoIncrementTableTest extends \PHPUnit\Framework\TestCase
{
    public static function crud_dataProvider(): array
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
                new Tables\TestAutoincrementCachedTable(Helpers\CacheHelper::getCache()),
                Entities\TestAutoincrementEntity::class,
            ],
            [
                new Tables\TestAutoincrementSdCachedTable(Helpers\CacheHelper::getCache()),
                Entities\TestAutoincrementSdEntity::class,
            ],
        ];
    }

    /**
     * @param class-string<Entities\TestAutoincrementEntity|Entities\TestAutoincrementSdEntity> $class
     */
    #[DataProvider('crud_dataProvider')]
    public function test_crud(AbstractTable&IAutoincrementTable $table, string $class): void
    {
        $table->truncate();
        $tableConfig = TableConfig::fromEntitySchema($class::schema());

        $entity = new $class(
            name: Helpers\StringHelper::getUniqueName(),
            is_test: true,
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

        $e1 = new $class(Helpers\StringHelper::getUniqueName());
        $e2 = new $class(Helpers\StringHelper::getUniqueName());

        $table->save($e1);
        $table->save($e2);
        $this->assertEntityExists($table, $e1);
        $this->assertEntityExists($table, $e2);

        $recentEntities = $table->findRecent(2, 0);
        $this->assertEquals($e2, $recentEntities[0]);
        $this->assertEquals($e1, $recentEntities[1]);
        $preLastEntity = $table->findRecent(1, 1);
        $this->assertEquals($e1, $preLastEntity[0]);

        if ($tableConfig->hasSoftDelete()) {
            $e1->name = 'Exception';
            $exceptionThrown = false;
            try {
                $table->deleteMany([$e1, $e2]);
            } catch (\Exception) {
                $exceptionThrown = true;
            }
            $this->assertTrue($exceptionThrown);
            $e1->name = Helpers\StringHelper::getUniqueName();
        }

        $table->deleteMany([$e1, $e2]);

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

    public function test_getMulti(): void
    {
        $table = new Tables\TestAutoincrementTable();

        $e1 = new Entities\TestAutoincrementEntity('name1');
        $e2 = new Entities\TestAutoincrementEntity('name2');
        $e3 = new Entities\TestAutoincrementEntity('name3');

        $table->save($e1);
        $table->save($e2);
        $table->save($e3);

        $multiResult = $table->findMulti([$e1->id, $e2->id, $e3->id]);
        $this->assertEquals($e1, $multiResult[$e1->id]);
        $this->assertEquals($e2, $multiResult[$e2->id]);
        $this->assertEquals($e3, $multiResult[$e3->id]);

        $this->assertEmpty($table->findMulti([]));
    }

    public function test_illegalGetMulti(): void
    {
        $table = new Tables\TestAutoincrementTable();
        $this->expectException(DbException::class);
        $table->findMulti(['a' => 1]);
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