<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractCachedTable;
use Composite\DB\AbstractTable;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Tables;
use Composite\Entity\AbstractEntity;

final class AbstractCachedTableTest extends BaseTableTest
{
    public function getOneCacheKey_dataProvider(): array
    {
        $cache = self::getCache();
        return [
            [
                new Tables\TestAutoincrementCachedTable($cache),
                Entities\TestAutoincrementEntity::fromArray(['id' => 123, 'name' => 'John']),
                'sqlite.TestAutoincrement.v1.o.id_123',
            ],
            [
                new Tables\TestCompositeCachedTable($cache),
                new Entities\TestCompositeEntity(user_id: 123, post_id: 456, message: 'Text'),
                'sqlite.TestComposite.v1.o.user_id_123_post_id_456',
            ],
            [
                new Tables\TestUniqueCachedTable($cache),
                new Entities\TestUniqueEntity(id: '123abc', name: 'John'),
                'sqlite.TestUnique.v1.o.id_123abc',
            ],
            [
                new Tables\TestUniqueCachedTable($cache),
                new Entities\TestUniqueEntity(
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
                '',
                [],
                'sqlite.TestAutoincrement.v1.c.all',
            ],
            [
                'name = :name',
                ['name' => 'John'],
                'sqlite.TestAutoincrement.v1.c.name_eq_john',
            ],
            [
                '     name        =     :name    ',
                ['name' => 'John'],
                'sqlite.TestAutoincrement.v1.c.name_eq_john',
            ],
            [
                'name=:name',
                ['name' => 'John'],
                'sqlite.TestAutoincrement.v1.c.name_eq_john',
            ],
            [
                'name = :name AND id > :id',
                ['name' => 'John', 'id' => 10],
                'sqlite.TestAutoincrement.v1.c.name_eq_john_and_id_gt_10',
            ],
        ];
    }

    /**
     * @dataProvider getCountCacheKey_dataProvider
     */
    public function test_getCountCacheKey(string $whereString, array $whereParams, string $expected): void
    {
        $table = new Tables\TestAutoincrementCachedTable(self::getCache());
        $reflectionMethod = new \ReflectionMethod($table, 'getCountCacheKey');
        $actual = $reflectionMethod->invoke($table, $whereString, $whereParams);
        $this->assertEquals($expected, $actual);
    }

    public function getListCacheKey_dataProvider(): array
    {
        return [
            [
                '',
                [],
                [],
                null,
                'sqlite.TestAutoincrement.v1.l.all',
            ],
            [
                '',
                [],
                [],
                10,
                'sqlite.TestAutoincrement.v1.l.all.limit_10',
            ],
            [
                '',
                [],
                ['id' => 'DESC'],
                10,
                'sqlite.TestAutoincrement.v1.l.all.ob_id_desc.limit_10',
            ],
            [
                'name = :name',
                ['name' => 'John'],
                [],
                null,
                'sqlite.TestAutoincrement.v1.l.name_eq_john',
            ],
            [
                'name = :name AND id > :id',
                ['name' => 'John', 'id' => 10],
                [],
                null,
                'sqlite.TestAutoincrement.v1.l.name_eq_john_and_id_gt_10',
            ],
            [
                'name = :name AND id > :id',
                ['name' => 'John', 'id' => 10],
                ['id' => 'ASC'],
                20,
                'bbcf331b765b682da02c4d21dbaa3342bf2c3f18', //sha1('sqlite.TestAutoincrement.v1.l.name_eq_john_and_id_gt_10.ob_id_asc.limit_20')
            ],
        ];
    }

    /**
     * @dataProvider getListCacheKey_dataProvider
     */
    public function test_getListCacheKey(string $whereString, array $whereArray, array $orderBy, ?int $limit, string $expected): void
    {
        $table = new Tables\TestAutoincrementCachedTable(self::getCache());
        $reflectionMethod = new \ReflectionMethod($table, 'getListCacheKey');
        $actual = $reflectionMethod->invoke($table, $whereString, $whereArray, $orderBy, $limit);
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
                [' a ', "id = 10 AND status='Active'   "],
                'sqlite.TestAutoincrement.v1.a.id_eq_10_and_status_eq_active',
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
        $table = new Tables\TestAutoincrementCachedTable(self::getCache());
        $reflectionMethod = new \ReflectionMethod($table, 'buildCacheKey');
        $actual = $reflectionMethod->invoke($table, ...$parts);
        $this->assertEquals($expected, $actual);
    }

    public function collectCacheKeysByEntity_dataProvider(): array
    {
        return [
            [
                new Entities\TestAutoincrementEntity(name: 'foo'),
                new Tables\TestAutoincrementCachedTable(self::getCache()),
                [
                    'sqlite.TestAutoincrement.v1.o.name_foo',
                    'sqlite.TestAutoincrement.v1.l.name_eq_foo',
                    'sqlite.TestAutoincrement.v1.c.name_eq_foo',
                ],
            ],
            [
                Entities\TestAutoincrementEntity::fromArray(['id' => 123, 'name' => 'bar']),
                new Tables\TestAutoincrementCachedTable(self::getCache()),
                [
                    'sqlite.TestAutoincrement.v1.o.name_bar',
                    'sqlite.TestAutoincrement.v1.l.name_eq_bar',
                    'sqlite.TestAutoincrement.v1.c.name_eq_bar',
                    'sqlite.TestAutoincrement.v1.o.id_123',
                ],
            ],
            [
                new Entities\TestUniqueEntity(id: '123abc', name: 'foo'),
                new Tables\TestUniqueCachedTable(self::getCache()),
                [
                    'sqlite.TestUnique.v1.l.name_eq_foo',
                    'sqlite.TestUnique.v1.c.name_eq_foo',
                    'sqlite.TestUnique.v1.o.id_123abc',
                ],
            ],
            [
                Entities\TestUniqueEntity::fromArray(['id' => '456def', 'name' => 'bar']),
                new Tables\TestUniqueCachedTable(self::getCache()),
                [
                    'sqlite.TestUnique.v1.l.name_eq_bar',
                    'sqlite.TestUnique.v1.c.name_eq_bar',
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