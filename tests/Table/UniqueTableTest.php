<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;
use Composite\DB\Tests\Helpers;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Tables;
use Composite\DB\Tests\TestStand\Interfaces\IUniqueTable;

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
                new Tables\TestUniqueSdTable(),
                Entities\TestUniqueSdEntity::class,
            ],
            [
                new Tables\TestUniqueCachedTable(Helpers\CacheHelper::getCache()),
                Entities\TestUniqueEntity::class,
            ],
            [
                new Tables\TestUniqueSdCachedTable(Helpers\CacheHelper::getCache()),
                Entities\TestUniqueSdEntity::class,
            ],
        ];
    }

    /**
     * @param class-string<Entities\TestUniqueEntity|Entities\TestUniqueSdEntity> $class
     * @dataProvider crud_dataProvider
     */
    public function test_crud(AbstractTable&IUniqueTable $table, string $class): void
    {
        $table->truncate();
        $tableConfig = TableConfig::fromEntitySchema($class::schema());

        $entity = new $class(
            id: uniqid(),
            name: Helpers\StringHelper::getUniqueName(),
        );
        $this->assertEntityNotExists($table, $entity);
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $entity->name = $entity->name . ' changed';
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $table->delete($entity);

        if ($tableConfig->hasSoftDelete()) {
            /** @var Entities\TestUniqueSdEntity $deletedEntity */
            $deletedEntity = $table->findByPk($entity->id);
            $this->assertTrue($deletedEntity->isDeleted());
        } else {
            $this->assertEntityNotExists($table, $entity);
        }
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