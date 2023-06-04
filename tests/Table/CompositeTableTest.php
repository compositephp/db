<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractTable;
use Composite\DB\Exceptions\DbException;
use Composite\DB\TableConfig;
use Composite\DB\Tests\Helpers;
use Composite\DB\Tests\TestStand\Tables;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Interfaces\ICompositeTable;

final class CompositeTableTest extends \PHPUnit\Framework\TestCase
{
    public static function crud_dataProvider(): array
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
                new Tables\TestCompositeCachedTable(Helpers\CacheHelper::getCache()),
                Entities\TestCompositeEntity::class,
            ],
            [
                new Tables\TestCompositeSdCachedTable(Helpers\CacheHelper::getCache()),
                Entities\TestCompositeSdEntity::class,
            ],
        ];
    }

    /**
     * @param class-string<Entities\TestCompositeEntity|Entities\TestCompositeSdEntity> $class
     * @dataProvider crud_dataProvider
     */
    public function test_crud(AbstractTable&ICompositeTable $table, string $class): void
    {
        $table->truncate();
        $tableConfig = TableConfig::fromEntitySchema($class::schema());

        $entity = new $class(
            user_id: mt_rand(1, 1000000),
            post_id: mt_rand(1, 1000000),
            message: Helpers\StringHelper::getUniqueName(),
        );
        $this->assertEntityNotExists($table, $entity);
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $entity->message = 'Bye World';
        $table->save($entity);
        $this->assertEntityExists($table, $entity);

        $table->delete($entity);

        if ($tableConfig->hasSoftDelete()) {
            /** @var Entities\TestCompositeSdEntity $deletedEntity */
            $deletedEntity = $table->findOne(user_id: $entity->user_id, post_id: $entity->post_id);
            $this->assertTrue($deletedEntity->isDeleted());
        } else {
            $this->assertEntityNotExists($table, $entity);
        }

        $e1 = new $class(
            user_id: mt_rand(1, 1000000),
            post_id: mt_rand(1, 1000000),
            message: Helpers\StringHelper::getUniqueName(),
        );
        $e2 = new $class(
            user_id: mt_rand(1, 1000000),
            post_id: mt_rand(1, 1000000),
            message: Helpers\StringHelper::getUniqueName(),
        );

        $table->saveMany([$e1, $e2]);
        $this->assertEntityExists($table, $e1);
        $this->assertEntityExists($table, $e2);

        $this->assertTrue($table->deleteMany([$e1, $e2]));

        if ($tableConfig->hasSoftDelete()) {
            /** @var Entities\TestCompositeSdEntity $deletedEntity1 */
            $deletedEntity1 = $table->findOne(user_id: $e1->user_id, post_id: $e1->post_id);
            $this->assertTrue($deletedEntity1->isDeleted());

            /** @var Entities\TestCompositeSdEntity $deletedEntity2 */
            $deletedEntity2 = $table->findOne(user_id: $e2->user_id, post_id: $e2->post_id);
            $this->assertTrue($deletedEntity2->isDeleted());
        } else {
            $this->assertEntityNotExists($table, $e1);
            $this->assertEntityNotExists($table, $e2);
        }
    }

    public function test_getMulti(): void
    {
        $table = new Tables\TestCompositeTable();
        $userId = mt_rand(1, 1000000);

        $e1 = new Entities\TestCompositeEntity(
            user_id: $userId,
            post_id: mt_rand(1, 1000000),
            message: Helpers\StringHelper::getUniqueName(),
        );

        $e2 = new Entities\TestCompositeEntity(
            user_id: $userId,
            post_id: mt_rand(1, 1000000),
            message: Helpers\StringHelper::getUniqueName(),
        );

        $e3 = new Entities\TestCompositeEntity(
            user_id: $userId,
            post_id: mt_rand(1, 1000000),
            message: Helpers\StringHelper::getUniqueName(),
        );

        $table->saveMany([$e1, $e2, $e3]);

        $multiResult = $table->findMulti([
            ['user_id' => $e1->user_id, 'post_id' => $e1->post_id],
            ['user_id' => $e2->user_id, 'post_id' => $e2->post_id],
            ['user_id' => $e3->user_id, 'post_id' => $e3->post_id],
        ]);
        $this->assertEquals($e1, $multiResult[$e1->post_id]);
        $this->assertEquals($e2, $multiResult[$e2->post_id]);
        $this->assertEquals($e3, $multiResult[$e3->post_id]);
    }

    public function test_illegalGetMulti(): void
    {
        $table = new Tables\TestCompositeTable();
        $this->expectException(DbException::class);
        $table->findMulti(['a']);
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