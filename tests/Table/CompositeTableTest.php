<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\Tests\TestStand\Tables;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Interfaces\ICompositeTable;

final class CompositeTableTest extends BaseTableTest
{
    public static function setUpBeforeClass(): void
    {
        (new Tables\TestCompositeTable())->init();
        (new Tables\TestCompositeSdTable())->init();
    }

    public function crud_dataProvider(): array
    {
        return [
            [
                new Tables\TestCompositeTable(),
                Entities\TestCompositeEntity::class,
            ],
            [
                new Tables\TestCompositeSdTable(),
                Entities\TestCompositeSdEntity::class,
            ],
            [
                new Tables\TestCompositeCachedTable(self::getCache()),
                Entities\TestCompositeEntity::class,
            ],
            [
                new Tables\TestCompositeSdCachedTable(self::getCache()),
                Entities\TestCompositeSdEntity::class,
            ],
        ];
    }

    /**
     * @dataProvider crud_dataProvider
     */
    public function test_crud(ICompositeTable $table, string $class): void
    {
        $table->truncate();

        $entity = new $class(
            user_id: mt_rand(1, 1000000),
            post_id: mt_rand(1, 1000000),
            message: $this->getUniqueName(),
        );
        $this->assertEntityNotExists($table, $entity);
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $entity->message = 'Bye World';
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $table->delete($entity);
        $this->assertEntityNotExists($table, $entity);

        $newEntity = new $entity(
            user_id: $entity->user_id,
            post_id: $entity->post_id,
            message: 'Hello User',
        );
        $table->save($newEntity);
        $this->assertEntityExists($table, $newEntity);
    }

    private function assertEntityExists(ICompositeTable $table, Entities\TestCompositeEntity $entity): void
    {
        $this->assertNotNull($table->findOne(user_id: $entity->user_id, post_id: $entity->post_id));
        $entityFound = array_filter(
            $table->findAllByUser($entity->user_id),
            fn ($item) => $item->toArray() === $entity->toArray()
        );
        $this->assertCount(1, $entityFound);
        $this->assertEquals(1, $table->countAllByUser($entity->user_id));
    }

    private function assertEntityNotExists(ICompositeTable $table, Entities\TestCompositeEntity $entity): void
    {
        $this->assertNull($table->findOne(user_id: $entity->user_id, post_id: $entity->post_id));
        $entityFound = array_filter(
            $table->findAllByUser($entity->user_id),
            fn ($item) => $item->toArray() === $entity->toArray()
        );
        $this->assertCount(0, $entityFound);
        $this->assertEquals(0, $table->countAllByUser($entity->user_id));
    }
}