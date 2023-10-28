<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractCachedTable;
use Composite\DB\AbstractTable;
use Composite\DB\Exceptions\DbException;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Tables;
use Composite\DB\Where;
use Composite\Entity\AbstractEntity;
use Composite\DB\Tests\Helpers;
use Ramsey\Uuid\Uuid;

final class AbstractCachedTableTest extends \PHPUnit\Framework\TestCase
{
    public static function getOneCacheKey_dataProvider(): array
    {
        $cache = Helpers\CacheHelper::getCache();
        $uuid = Uuid::uuid4();
        $uuidCacheKey = str_replace('-', '_', (string)$uuid);
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
                new Entities\TestUniqueEntity(id: $uuid, name: 'John'),
                'sqlite.TestUnique.v1.o.id_' . $uuidCacheKey,
            ],
            [
                new Tables\TestCompositeCachedTable($cache),
                new Entities\TestCompositeEntity(user_id: PHP_INT_MAX, post_id: PHP_INT_MAX, message: 'Text'),
                '69b5bbf599d78f0274feb5cb0e6424f35cca0b57',
            ],
        ];
    }

    /**
     * @dataProvider getOneCacheKey_dataProvider
     */
    public function test_getOneCacheKey(AbstractCachedTable $table, AbstractEntity $object, string $expected): void
    {
        $reflectionMethod = new \ReflectionMethod($table, 'getOneCacheKey');
        $actual = $reflectionMethod->invoke($table, $object);
        $this->assertEquals($expected, $actual);
    }


    public static function getCountCacheKey_dataProvider(): array
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
        $table = new Tables\TestAutoincrementCachedTable(Helpers\CacheHelper::getCache());
        $reflectionMethod = new \ReflectionMethod($table, 'getCountCacheKey');
        $actual = $reflectionMethod->invoke($table, new Where($whereString, $whereParams));
        $this->assertEquals($expected, $actual);
    }

    public static function getListCacheKey_dataProvider(): array
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
        $table = new Tables\TestAutoincrementCachedTable(Helpers\CacheHelper::getCache());
        $reflectionMethod = new \ReflectionMethod($table, 'getListCacheKey');
        $actual = $reflectionMethod->invoke($table, new Where($whereString, $whereArray), $orderBy, $limit);
        $this->assertEquals($expected, $actual);
    }


    public static function getCustomCacheKey_dataProvider(): array
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
        $table = new Tables\TestAutoincrementCachedTable(Helpers\CacheHelper::getCache());
        $reflectionMethod = new \ReflectionMethod($table, 'buildCacheKey');
        $actual = $reflectionMethod->invoke($table, ...$parts);
        $this->assertEquals($expected, $actual);
    }

    public static function collectCacheKeysByEntity_dataProvider(): array
    {
        $uuid = Uuid::uuid4();
        $uuidCacheKey = str_replace('-', '_', (string)$uuid);
        return [
            [
                new Entities\TestAutoincrementEntity(name: 'foo'),
                new Tables\TestAutoincrementCachedTable(Helpers\CacheHelper::getCache()),
                [
                    'sqlite.TestAutoincrement.v1.o.name_foo',
                    'sqlite.TestAutoincrement.v1.l.name_eq_foo',
                    'sqlite.TestAutoincrement.v1.c.name_eq_foo',
                ],
            ],
            [
                Entities\TestAutoincrementEntity::fromArray(['id' => 123, 'name' => 'bar']),
                new Tables\TestAutoincrementCachedTable(Helpers\CacheHelper::getCache()),
                [
                    'sqlite.TestAutoincrement.v1.o.name_bar',
                    'sqlite.TestAutoincrement.v1.l.name_eq_bar',
                    'sqlite.TestAutoincrement.v1.c.name_eq_bar',
                    'sqlite.TestAutoincrement.v1.o.id_123',
                ],
            ],
            [
                new Entities\TestUniqueEntity(id: $uuid, name: 'foo'),
                new Tables\TestUniqueCachedTable(Helpers\CacheHelper::getCache()),
                [
                    'sqlite.TestUnique.v1.l.name_eq_foo',
                    'sqlite.TestUnique.v1.c.name_eq_foo',
                    'sqlite.TestUnique.v1.o.id_' . $uuidCacheKey,
                ],
            ],
            [
                Entities\TestUniqueEntity::fromArray(['id' => $uuid, 'name' => 'bar']),
                new Tables\TestUniqueCachedTable(Helpers\CacheHelper::getCache()),
                [
                    'sqlite.TestUnique.v1.l.name_eq_bar',
                    'sqlite.TestUnique.v1.c.name_eq_bar',
                    'sqlite.TestUnique.v1.o.id_' . $uuidCacheKey,
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

    public function test_findMulti(): void
    {
        $table = new Tables\TestAutoincrementCachedTable(Helpers\CacheHelper::getCache());
        $e1 = new Entities\TestAutoincrementEntity('John');
        $e2 = new Entities\TestAutoincrementEntity('Constantine');

        $table->save($e1);
        $table->save($e2);

        $multi1 = $table->findMulti([$e1->id]);
        $this->assertEquals($e1, $multi1[0]);

        $multi2 = $table->findMulti([$e1->id, $e2->id]);
        $this->assertEquals($e1, $multi2[0]);
        $this->assertEquals($e2, $multi2[1]);

        $e11 = $table->findByPk($e1->id);
        $this->assertEquals($e1, $e11);

        $e111 = $table->findByPk($e1->id);
        $this->assertEquals($e1, $e111);
    }
}