<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\Helpers;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Tables;
use Composite\DB\Tests\TestStand\Interfaces\IUniqueTable;
use Ramsey\Uuid\Uuid;

final class UniqueTableTest extends \PHPUnit\Framework\TestCase
{
    public static function crud_dataProvider(): array
    {
        return [
            [
                new Tables\TestUniqueTable(),
                Entities\TestUniqueEntity::class,
            ],
            [
                new Tables\TestUniqueCachedTable(Helpers\CacheHelper::getCache()),
                Entities\TestUniqueEntity::class,
            ],
        ];
    }

    /**
     * @param class-string<Entities\TestUniqueEntity> $class
     * @dataProvider crud_dataProvider
     */
    public function test_crud(AbstractTable&IUniqueTable $table, string $class): void
    {
        $table->truncate();

        $entity = new $class(
            id: Uuid::uuid4(),
            name: Helpers\StringHelper::getUniqueName(),
        );
        $this->assertEntityNotExists($table, $entity);
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $entity->name = $entity->name . ' changed';
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $table->delete($entity);
        $this->assertEntityNotExists($table, $entity);
    }

    public function test_multiSave(): void
    {
        $e1 = new Entities\TestUniqueEntity(
            id: Uuid::uuid4(),
            name: Helpers\StringHelper::getUniqueName(),
        );
        $e2 = new Entities\TestUniqueEntity(
            id: Uuid::uuid4(),
            name: Helpers\StringHelper::getUniqueName(),
        );
        $e3 = new Entities\TestUniqueEntity(
            id: Uuid::uuid4(),
            name: Helpers\StringHelper::getUniqueName(),
        );
        $e4 = new Entities\TestUniqueEntity(
            id: Uuid::uuid4(),
            name: Helpers\StringHelper::getUniqueName(),
        );
        $table = new Tables\TestUniqueTable();
        $table->saveMany([$e1, $e2]);

        $this->assertEntityExists($table, $e1);
        $this->assertEntityExists($table, $e2);

        $e1->resetChangedColumns();
        $e2->resetChangedColumns();

        $e1->name = 'Exception';

        $exceptionThrown = false;
        try {
            $table->saveMany([$e1, $e2, $e3, $e4]);
        } catch (\Exception) {
            $exceptionThrown = true;
        }
        $this->assertTrue($exceptionThrown);
        $this->assertEntityNotExists($table, $e3);
        $this->assertEntityNotExists($table, $e4);

        $e1->name = 'NonException';

        $table->saveMany([$e1, $e2, $e3, $e4]);

        $this->assertEntityExists($table, $e1);
        $this->assertEntityExists($table, $e2);
        $this->assertEntityExists($table, $e3);
        $this->assertEntityExists($table, $e4);
    }

    private function assertEntityExists(IUniqueTable $table, Entities\TestUniqueEntity $entity): void
    {
        $this->assertNotNull($table->findByPk($entity->id));
        $entityFound = array_filter(
            $table->findAllByName($entity->name),
            fn ($item) => $item->toArray() === $entity->toArray()
        );
        $this->assertCount(1, $entityFound);
        $this->assertEquals(1, $table->countAllByName($entity->name));
    }

    private function assertEntityNotExists(IUniqueTable $table, Entities\TestUniqueEntity $entity): void
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