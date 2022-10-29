<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractCachedTable;
use Composite\DB\AbstractTable;
use Composite\DB\Tests\Table\TestStand\Entities;
use Composite\DB\Tests\Table\TestStand\Tables;
use Composite\Entity\AbstractEntity;
use Cycle\Database\Query\SelectQuery;

final class AbstractCachedTableTest extends BaseTableTest
{
    public function getOneCacheKey_dataProvider(): array
    {
        $dbManager = self::getDatabaseManager();
        $cache = self::getCache();
        return [
            [
                new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementCachedTable($dbManager, $cache),
                \Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity::fromArray(['id' => 123, 'name' => 'John']),
                'sqlite.TestAutoincrement.v1.o.id_123',
            ],
            [
                new \Composite\DB\Tests\TestStand\Tables\TestCompositeCachedTable($dbManager, $cache),
                new \Composite\DB\Tests\TestStand\Entities\TestCompositeEntity(user_id: 123, post_id: 456, message: 'Text'),
                'sqlite.TestComposite.v1.o.user_id_123_post_id_456',
            ],
            [
                new \Composite\DB\Tests\TestStand\Tables\TestUniqueCachedTable($dbManager, $cache),
                new \Composite\DB\Tests\TestStand\Entities\TestUniqueEntity(id: '123abc', name: 'John'),
                'sqlite.TestUnique.v1.o.id_123abc',
            ],
            [
                new \Composite\DB\Tests\TestStand\Tables\TestUniqueCachedTable($dbManager, $cache),
                new \Composite\DB\Tests\TestStand\Entities\TestUniqueEntity(
                    id: implode('', array_fill(0, 100, 'a')),
                    name: 'John',
                ),
                'ed66f06444d851a981a9ddcecbbf4d5860cd3131',
            ],
        ];
    }

    /**
     * @dataProvider getOneCacheKey_dataProvider
     */
    public function test_getOneCacheKey(AbstractTable $table, AbstractEntity $object, string $expected): void
    {
        $reflectionMethod = new \ReflectionMethod($table, 'getOneCacheKey');
        $actual = $reflectionMethod->invoke($table, $object);
        $this->assertEquals($expected, $actual);
    }


    public function getCountCacheKey_dataProvider(): array
    {
        return [
            [
                [],
                'sqlite.TestAutoincrement.v1.c.all',
            ],
            [
                ['name' => 'John'],
                'sqlite.TestAutoincrement.v1.c.name_John',
            ],
            [
                ['name' => 'John', 'id' => ['>' => 10]],
                'sqlite.TestAutoincrement.v1.c.name_John_id_gt_10',
            ],
        ];
    }

    /**
     * @dataProvider getCountCacheKey_dataProvider
     */
    public function test_getCountCacheKey(array $condition, string $expected): void
    {
        $table = new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementCachedTable(self::getDatabaseManager(), self::getCache());
        $reflectionMethod = new \ReflectionMethod($table, 'getCountCacheKey');
        $actual = $reflectionMethod->invoke($table, $condition);
        $this->assertEquals($expected, $actual);
    }

    public function getListCacheKey_dataProvider(): array
    {
        return [
            [
                [],
                [],
                null,
                'sqlite.TestAutoincrement.v1.l.all',
            ],
            [
                [],
                [],
                10,
                'sqlite.TestAutoincrement.v1.l.all.limit_10',
            ],
            [
                [],
                ['id' => SelectQuery::SORT_DESC],
                10,
                'sqlite.TestAutoincrement.v1.l.all.ob_id_DESC.limit_10',
            ],
            [
                ['name' => 'John'],
                [],
                null,
                'sqlite.TestAutoincrement.v1.l.name_John',
            ],
            [
                ['name' => 'John', 'id' => ['>' => 10]],
                [],
                null,
                'sqlite.TestAutoincrement.v1.l.name_John_id_gt_10',
            ],
            [
                ['name' => 'John', 'id' => ['>' => 10]],
                ['id' => SelectQuery::SORT_ASC],
                20,
                'fd3fb3ff3f613c0e94a08a838372ee611e1aa193',
            ],
        ];
    }

    /**
     * @dataProvider getListCacheKey_dataProvider
     */
    public function test_getListCacheKey(array $condition, array $orderBy, ?int $limit, string $expected): void
    {
        $table = new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementCachedTable(self::getDatabaseManager(), self::getCache());
        $reflectionMethod = new \ReflectionMethod($table, 'getListCacheKey');
        $actual = $reflectionMethod->invoke($table, $condition, $orderBy, $limit);
        $this->assertEquals($expected, $actual);
    }


    public function getCustomCacheKey_dataProvider(): array
    {
        return [
            [
                [],
                'sqlite.TestAutoincrement.v1.all',
            ],
            [
                ['custom'],
                'sqlite.TestAutoincrement.v1.custom',
            ],
            [
                ['a', '', false, 2, null, 0, 'b'],
                'sqlite.TestAutoincrement.v1.a.2.b',
            ],
            [
                ['arr', [1, 2, 3], null, ['a' => 123, 'b' => 456]],
                'sqlite.TestAutoincrement.v1.arr.1_2_3.a_123_b_456',
            ],
        ];
    }

    /**
     * @dataProvider getCustomCacheKey_dataProvider
     */
    public function test_getCustomCacheKey(array $parts, string $expected): void
    {
        $table = new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementCachedTable(self::getDatabaseManager(), self::getCache());
        $reflectionMethod = new \ReflectionMethod($table, 'buildCacheKey');
        $actual = $reflectionMethod->invoke($table, ...$parts);
        $this->assertEquals($expected, $actual);
    }

    public function collectCacheKeysByEntity_dataProvider(): array
    {
        return [
            [
                new \Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity(name: 'foo'),
                new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementCachedTable(self::getDatabaseManager(), self::getCache()),
                [
                    'sqlite.TestAutoincrement.v1.o.name_foo',
                    'sqlite.TestAutoincrement.v1.l.name_foo',
                    'sqlite.TestAutoincrement.v1.c.name_foo',
                ],
            ],
            [
                \Composite\DB\Tests\TestStand\Entities\TestAutoincrementEntity::fromArray(['id' => 123, 'name' => 'bar']),
                new \Composite\DB\Tests\TestStand\Tables\TestAutoincrementCachedTable(self::getDatabaseManager(), self::getCache()),
                [
                    'sqlite.TestAutoincrement.v1.o.name_bar',
                    'sqlite.TestAutoincrement.v1.l.name_bar',
                    'sqlite.TestAutoincrement.v1.c.name_bar',
                    'sqlite.TestAutoincrement.v1.o.id_123',
                ],
            ],
            [
                new \Composite\DB\Tests\TestStand\Entities\TestUniqueEntity(id: '123abc', name: 'foo'),
                new \Composite\DB\Tests\TestStand\Tables\TestUniqueCachedTable(self::getDatabaseManager(), self::getCache()),
                [
                    'sqlite.TestUnique.v1.l.name_foo',
                    'sqlite.TestUnique.v1.c.name_foo',
                    'sqlite.TestUnique.v1.o.id_123abc',
                ],
            ],
            [
                \Composite\DB\Tests\TestStand\Entities\TestUniqueEntity::fromArray(['id' => '456def', 'name' => 'bar']),
                new \Composite\DB\Tests\TestStand\Tables\TestUniqueCachedTable(self::getDatabaseManager(), self::getCache()),
                [
                    'sqlite.TestUnique.v1.l.name_bar',
                    'sqlite.TestUnique.v1.c.name_bar',
                    'sqlite.TestUnique.v1.o.id_456def',
                ],
            ],
        ];
    }

    /**
     * @dataProvider collectCacheKeysByEntity_dataProvider
     */
    public function test_collectCacheKeysByEntity(AbstractEntity $entity, AbstractCachedTable $table, array $expected): void
    {
        $reflectionMethod = new \ReflectionMethod($table, 'collectCacheKeysByEntity');
        $actual = $reflectionMethod->invoke($table, $entity);
        $this->assertEquals($expected, $actual);
    }
}