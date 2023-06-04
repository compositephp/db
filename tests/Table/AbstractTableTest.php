<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\AbstractTable;
use Composite\DB\Tests\TestStand\Entities;
use Composite\DB\Tests\TestStand\Tables;
use Composite\Entity\AbstractEntity;
use Composite\Entity\Exceptions\EntityException;

final class AbstractTableTest extends \PHPUnit\Framework\TestCase
{
    public static function getPkCondition_dataProvider(): array
    {
        return [
            [
                new Tables\TestAutoincrementTable(),
                Entities\TestAutoincrementEntity::fromArray(['id' => 123, 'name' => 'John']),
                ['id' => 123],
            ],
            [
                new Tables\TestAutoincrementTable(),
                456,
                ['id' => 456],
            ],
            [
                new Tables\TestCompositeTable(),
                new Entities\TestCompositeEntity(user_id: 123, post_id: 456, message: 'Text'),
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestCompositeTable(),
                ['user_id' => 123, 'post_id' => 456],
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestUniqueTable(),
                new Entities\TestUniqueEntity(id: '123abc', name: 'John'),
                ['id' => '123abc'],
            ],
            [
                new Tables\TestUniqueTable(),
                '123abc',
                ['id' => '123abc'],
            ],
            [
                new Tables\TestAutoincrementSdTable(),
                Entities\TestAutoincrementSdEntity::fromArray(['id' => 123, 'name' => 'John']),
                ['id' => 123],
            ],
            [
                new Tables\TestCompositeSdTable(),
                new Entities\TestCompositeSdEntity(user_id: 123, post_id: 456, message: 'Text'),
                ['user_id' => 123, 'post_id' => 456],
            ],
            [
                new Tables\TestUniqueSdTable(),
                new Entities\TestUniqueSdEntity(id: '123abc', name: 'John'),
                ['id' => '123abc'],
            ],
        ];
    }

    /**
     * @dataProvider getPkCondition_dataProvider
     */
    public function test_getPkCondition(AbstractTable $table, int|string|array|AbstractEntity $object, array $expected): void
    {
        $reflectionMethod = new \ReflectionMethod($table, 'getPkCondition');
        $actual = $reflectionMethod->invoke($table, $object);
        $this->assertEquals($expected, $actual);
    }

    public function test_illegalEntitySave(): void
    {
        $entity = new Entities\TestAutoincrementEntity(name: 'Foo');
        $compositeTable = new Tables\TestUniqueTable();

        $this->expectException(EntityException::class);
        $compositeTable->save($entity);
    }

    public function test_illegalCreateEntity(): void
    {
        $table = new Tables\TestStrictTable();
        $null = $table->buildEntity(['dti1' => 'abc']);
        $this->assertNull($null);

        $empty = $table->buildEntities([['dti1' => 'abc']]);
        $this->assertEmpty($empty);

        $empty = $table->buildEntities([]);
        $this->assertEmpty($empty);

        $empty = $table->buildEntities(false);
        $this->assertEmpty($empty);

        $empty = $table->buildEntities('abc');
        $this->assertEmpty($empty);

        $empty = $table->buildEntities(['abc']);
        $this->assertEmpty($empty);
    }
}