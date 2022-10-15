<?php declare(strict_types=1);

namespace Composite\DB\Tests\Migrations;

use Composite\DB\Migrations\CycleBridge;
use Composite\DB\Tests\Entity\TestStand\TestBackedIntEnum;
use Composite\DB\Tests\Entity\TestStand\TestBackedStringEnum;
use Composite\DB\Tests\Entity\TestStand\TestDiversityEntity;
use Composite\DB\Tests\Entity\TestStand\TestEntity;
use Composite\DB\Tests\Entity\TestStand\TestUnitEnum;
use Composite\DB\Tests\Table\BaseTableTest;
use Composite\DB\Tests\Table\TestStand\Entities;
use Cycle\Database\Driver\SQLite\Schema;
use Cycle\Database\Injection\Fragment;

final class CycleBridgeTest extends BaseTableTest
{
    public function generateTable_dataProvider(): array
    {
        return [
            [
                'class' => Entities\TestAutoincrementEntity::class,
                'expectedColumns' => [
                    'id' => (new Schema\SQLiteColumn('TestAutoincrement', 'id'))
                        ->primary(),
                    'name' => (new Schema\SQLiteColumn('TestAutoincrement', 'name'))
                        ->string()
                        ->nullable(false),
                    'created_at' => (new Schema\SQLiteColumn('TestAutoincrement', 'created_at'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                ],
                'expectedPrimaryKeys' => ['id'],
                'expectedIndexes' => [],
            ],
            [
                'class' => Entities\TestAutoincrementSdEntity::class,
                'expectedColumns' => [
                    'id' => (new Schema\SQLiteColumn('TestAutoIncrementSoftDelete', 'id'))
                        ->primary(),
                    'name' => (new Schema\SQLiteColumn('TestAutoIncrementSoftDelete', 'name'))
                        ->string()
                        ->nullable(false),
                    'created_at' => (new Schema\SQLiteColumn('TestAutoIncrementSoftDelete', 'created_at'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                    'deleted_at' => (new Schema\SQLiteColumn('TestAutoIncrementSoftDelete', 'deleted_at'))
                        ->timestamp(),
                ],
                'expectedPrimaryKeys' => ['id'],
                'expectedIndexes' => [],
            ],
            [
                'class' => Entities\TestUniqueEntity::class,
                'expectedColumns' => [
                    'id' => (new Schema\SQLiteColumn('TestUnique', 'id'))
                        ->string()
                        ->nullable(false),
                    'name' => (new Schema\SQLiteColumn('TestUnique', 'name'))
                        ->string()
                        ->nullable(false),
                    'created_at' => (new Schema\SQLiteColumn('TestUnique', 'created_at'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                ],
                'expectedPrimaryKeys' => ['id'],
                'expectedIndexes' => [],
            ],
            [
                'class' => Entities\TestUniqueSdEntity::class,
                'expectedColumns' => [
                    'id' => (new Schema\SQLiteColumn('TestUniqueSoftDelete', 'id'))
                        ->string()
                        ->nullable(false),
                    'name' => (new Schema\SQLiteColumn('TestUniqueSoftDelete', 'name'))
                        ->string()
                        ->nullable(false),
                    'created_at' => (new Schema\SQLiteColumn('TestUniqueSoftDelete', 'created_at'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                    'deleted_at' => (new Schema\SQLiteColumn('TestUniqueSoftDelete', 'deleted_at'))
                        ->timestamp(),
                ],
                'expectedPrimaryKeys' => ['id', 'deleted_at'],
                'expectedIndexes' => [],
            ],
            [
                'class' => Entities\TestCompositeEntity::class,
                'expectedColumns' => [
                    'user_id' => (new Schema\SQLiteColumn('TestComposite', 'user_id'))
                        ->integer()
                        ->nullable(false),
                    'post_id' => (new Schema\SQLiteColumn('TestComposite', 'post_id'))
                        ->integer()
                        ->nullable(false),
                    'message' => (new Schema\SQLiteColumn('TestComposite', 'message'))
                        ->string()
                        ->nullable(false),
                    'created_at' => (new Schema\SQLiteColumn('TestComposite', 'created_at'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                ],
                'expectedPrimaryKeys' => ['user_id', 'post_id'],
                'expectedIndexes' => [],
            ],
            [
                'class' => Entities\TestCompositeSdEntity::class,
                'expectedColumns' => [
                    'user_id' => (new Schema\SQLiteColumn('TestCompositeSoftDelete', 'user_id'))
                        ->integer()
                        ->nullable(false),
                    'post_id' => (new Schema\SQLiteColumn('TestCompositeSoftDelete', 'post_id'))
                        ->integer()
                        ->nullable(false),
                    'message' => (new Schema\SQLiteColumn('TestCompositeSoftDelete', 'message'))
                        ->string()
                        ->nullable(false),
                    'created_at' => (new Schema\SQLiteColumn('TestCompositeSoftDelete', 'created_at'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                    'deleted_at' => (new Schema\SQLiteColumn('TestCompositeSoftDelete', 'deleted_at'))
                        ->timestamp(),
                ],
                'expectedPrimaryKeys' => ['user_id', 'post_id', 'deleted_at'],
                'expectedIndexes' => [],
            ],
            [
                'class' => TestEntity::class,
                'expectedColumns' => [
                    'str' => (new Schema\SQLiteColumn('Test', 'str'))
                        ->string()
                        ->nullable(false)
                        ->defaultValue('foo'),
                    'int' => (new Schema\SQLiteColumn('Test', 'int'))
                        ->integer()
                        ->nullable(false)
                        ->defaultValue(999),
                    'float' => (new Schema\SQLiteColumn('Test', 'float'))
                        ->float()
                        ->nullable(false)
                        ->defaultValue(9.99),
                    'bool' => (new Schema\SQLiteColumn('Test', 'bool'))
                        ->boolean()
                        ->nullable(false)
                        ->defaultValue(true),
                    'arr' => (new Schema\SQLiteColumn('Test', 'arr'))
                        ->json()
                        ->nullable(false)
                        ->defaultValue('[]'),
                    'object' => (new Schema\SQLiteColumn('Test', 'object'))
                        ->json()
                        ->nullable(false)
                        ->defaultValue('{}'),
                    'date_time' => (new Schema\SQLiteColumn('Test', 'date_time'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                    'date_time_immutable' => (new Schema\SQLiteColumn('Test', 'date_time_immutable'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                    'backed_enum' => (new Schema\SQLiteColumn('Test', 'backed_enum'))
                        ->enum(
                            array_map(
                                fn (TestBackedStringEnum $enum) => $enum->value,
                                TestBackedStringEnum::cases(),
                            )
                        )
                        ->nullable(false)
                        ->defaultValue(TestBackedStringEnum::Foo->value),
                    'unit_enum' => (new Schema\SQLiteColumn('Test', 'unit_enum'))
                        ->enum(
                            array_map(
                                fn (TestUnitEnum $enum) => $enum->name,
                                TestUnitEnum::cases(),
                            )
                        )
                        ->nullable(false)
                        ->defaultValue(TestUnitEnum::Bar->name),
                    'entity' => (new Schema\SQLiteColumn('Test', 'entity'))
                        ->json()
                        ->nullable(false)
                        ->defaultValue('{"str":"foo","number":123}'),
                    'castable' => (new Schema\SQLiteColumn('Test', 'castable'))
                        ->integer()
                        ->nullable(false)
                        ->defaultValue(946684801),
                ],
                'expectedPrimaryKeys' => ['str'],
                'expectedIndexes' => [],
            ],
            [
                'class' => TestDiversityEntity::class,
                'expectedColumns' => [
                    'id' => (new Schema\SQLiteColumn('Diversity', 'id'))
                        ->primary(),
                    'str1' => (new Schema\SQLiteColumn('Diversity', 'str1'))
                        ->string()
                        ->nullable(false),
                    'str2' => (new Schema\SQLiteColumn('Diversity', 'str2'))
                        ->string()
                        ->nullable(true),
                    'str3' => (new Schema\SQLiteColumn('Diversity', 'str3'))
                        ->string()
                        ->nullable(false)
                        ->defaultValue('str3 def'),
                    'str4' => (new Schema\SQLiteColumn('Diversity', 'str4'))
                        ->string()
                        ->nullable(true)
                        ->defaultValue(''),
                    'str5' => (new Schema\SQLiteColumn('Diversity', 'str5'))
                        ->string()
                        ->nullable(true)
                        ->defaultValue(null),
                    
                    'int1' => (new Schema\SQLiteColumn('Diversity', 'int1'))
                        ->integer()
                        ->nullable(false),
                    'int2' => (new Schema\SQLiteColumn('Diversity', 'int2'))
                        ->integer()
                        ->nullable(true),
                    'int3' => (new Schema\SQLiteColumn('Diversity', 'int3'))
                        ->integer()
                        ->nullable(false)
                        ->defaultValue(33),
                    'int4' => (new Schema\SQLiteColumn('Diversity', 'int4'))
                        ->integer()
                        ->nullable(true)
                        ->defaultValue(44),
                    'int5' => (new Schema\SQLiteColumn('Diversity', 'int5'))
                        ->integer()
                        ->nullable(true)
                        ->defaultValue(null),

                    'float1' => (new Schema\SQLiteColumn('Diversity', 'float1'))
                        ->float()
                        ->nullable(false),
                    'float2' => (new Schema\SQLiteColumn('Diversity', 'float2'))
                        ->float()
                        ->nullable(true),
                    'float3' => (new Schema\SQLiteColumn('Diversity', 'float3'))
                        ->float()
                        ->nullable(false)
                        ->defaultValue(3.9),
                    'float4' => (new Schema\SQLiteColumn('Diversity', 'float4'))
                        ->float()
                        ->nullable(true)
                        ->defaultValue(4.9),
                    'float5' => (new Schema\SQLiteColumn('Diversity', 'float5'))
                        ->float()
                        ->nullable(true)
                        ->defaultValue(null),

                    'bool1' => (new Schema\SQLiteColumn('Diversity', 'bool1'))
                        ->boolean()
                        ->nullable(false),
                    'bool2' => (new Schema\SQLiteColumn('Diversity', 'bool2'))
                        ->boolean()
                        ->nullable(true),
                    'bool3' => (new Schema\SQLiteColumn('Diversity', 'bool3'))
                        ->boolean()
                        ->nullable(false)
                        ->defaultValue(true),
                    'bool4' => (new Schema\SQLiteColumn('Diversity', 'bool4'))
                        ->boolean()
                        ->nullable(true)
                        ->defaultValue(false),
                    'bool5' => (new Schema\SQLiteColumn('Diversity', 'bool5'))
                        ->boolean()
                        ->nullable(true)
                        ->defaultValue(null),

                    'arr1' => (new Schema\SQLiteColumn('Diversity', 'arr1'))
                        ->json()
                        ->nullable(false),
                    'arr2' => (new Schema\SQLiteColumn('Diversity', 'arr2'))
                        ->json()
                        ->nullable(true),
                    'arr3' => (new Schema\SQLiteColumn('Diversity', 'arr3'))
                        ->json()
                        ->nullable(false)
                        ->defaultValue('[11,22,33]'),
                    'arr4' => (new Schema\SQLiteColumn('Diversity', 'arr4'))
                        ->json()
                        ->nullable(true)
                        ->defaultValue('[]'),
                    'arr5' => (new Schema\SQLiteColumn('Diversity', 'arr5'))
                        ->json()
                        ->nullable(true)
                        ->defaultValue(null),

                    'benum_str1' => (new Schema\SQLiteColumn('Diversity', 'benum_str1'))
                        ->enum(TestBackedStringEnum::getCycleMigrationValue())
                        ->nullable(false),
                    'benum_str2' => (new Schema\SQLiteColumn('Diversity', 'benum_str2'))
                        ->enum(TestBackedStringEnum::getCycleMigrationValue())
                        ->nullable(true),
                    'benum_str3' => (new Schema\SQLiteColumn('Diversity', 'benum_str3'))
                        ->enum(TestBackedStringEnum::getCycleMigrationValue())
                        ->nullable(false)
                        ->defaultValue(TestBackedStringEnum::Foo->value),
                    'benum_str4' => (new Schema\SQLiteColumn('Diversity', 'benum_str4'))
                        ->enum(TestBackedStringEnum::getCycleMigrationValue())
                        ->nullable(true)
                        ->defaultValue(TestBackedStringEnum::Bar->value),
                    'benum_str5' => (new Schema\SQLiteColumn('Diversity', 'benum_str5'))
                        ->enum(TestBackedStringEnum::getCycleMigrationValue())
                        ->nullable(true)
                        ->defaultValue(null),

                    'benum_int1' => (new Schema\SQLiteColumn('Diversity', 'benum_int1'))
                        ->integer()
                        ->nullable(false),
                    'benum_int2' => (new Schema\SQLiteColumn('Diversity', 'benum_int2'))
                        ->integer()
                        ->nullable(true),
                    'benum_int3' => (new Schema\SQLiteColumn('Diversity', 'benum_int3'))
                        ->integer()
                        ->nullable(false)
                        ->defaultValue(TestBackedIntEnum::FooInt->value),
                    'benum_int4' => (new Schema\SQLiteColumn('Diversity', 'benum_int4'))
                        ->integer()
                        ->nullable(true)
                        ->defaultValue(TestBackedIntEnum::BarInt->value),
                    'benum_int5' => (new Schema\SQLiteColumn('Diversity', 'benum_int5'))
                        ->integer()
                        ->nullable(true)
                        ->defaultValue(null),

                    'uenum1' => (new Schema\SQLiteColumn('Diversity', 'uenum1'))
                        ->enum(TestUnitEnum::getCycleMigrationValue())
                        ->nullable(false),
                    'uenum2' => (new Schema\SQLiteColumn('Diversity', 'uenum2'))
                        ->enum(TestUnitEnum::getCycleMigrationValue())
                        ->nullable(true),
                    'uenum3' => (new Schema\SQLiteColumn('Diversity', 'uenum3'))
                        ->enum(TestUnitEnum::getCycleMigrationValue())
                        ->nullable(false)
                        ->defaultValue(TestUnitEnum::Foo->name),
                    'uenum4' => (new Schema\SQLiteColumn('Diversity', 'uenum4'))
                        ->enum(TestUnitEnum::getCycleMigrationValue())
                        ->nullable(true)
                        ->defaultValue(TestUnitEnum::Bar->name),
                    'uenum5' => (new Schema\SQLiteColumn('Diversity', 'uenum5'))
                        ->enum(TestUnitEnum::getCycleMigrationValue())
                        ->nullable(true)
                        ->defaultValue(null),

                    'obj1' => (new Schema\SQLiteColumn('Diversity', 'obj1'))
                        ->json()
                        ->nullable(false),
                    'obj2' => (new Schema\SQLiteColumn('Diversity', 'obj2'))
                        ->json()
                        ->nullable(true),
                    'obj3' => (new Schema\SQLiteColumn('Diversity', 'obj3'))
                        ->json()
                        ->nullable(false)
                        ->defaultValue('{}'),
                    'obj4' => (new Schema\SQLiteColumn('Diversity', 'obj4'))
                        ->json()
                        ->nullable(true)
                        ->defaultValue('{}'),
                    'obj5' => (new Schema\SQLiteColumn('Diversity', 'obj5'))
                        ->json()
                        ->nullable(true)
                        ->defaultValue(null),

                    'entity1' => (new Schema\SQLiteColumn('Diversity', 'entity1'))
                        ->json()
                        ->nullable(false),
                    'entity2' => (new Schema\SQLiteColumn('Diversity', 'entity2'))
                        ->json()
                        ->nullable(true),
                    'entity3' => (new Schema\SQLiteColumn('Diversity', 'entity3'))
                        ->json()
                        ->nullable(false)
                        ->defaultValue('{"str":"foo","number":123}'),
                    'entity4' => (new Schema\SQLiteColumn('Diversity', 'entity4'))
                        ->json()
                        ->nullable(true)
                        ->defaultValue('{"str":"foo","number":456}'),
                    'entity5' => (new Schema\SQLiteColumn('Diversity', 'entity5'))
                        ->json()
                        ->nullable(true)
                        ->defaultValue(null),

                    'dt1' => (new Schema\SQLiteColumn('Diversity', 'dt1'))
                        ->timestamp()
                        ->nullable(false),
                    'dt2' => (new Schema\SQLiteColumn('Diversity', 'dt2'))
                        ->timestamp()
                        ->nullable(true),
                    'dt3' => (new Schema\SQLiteColumn('Diversity', 'dt3'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue('2000-01-01 00:00:00.000000'),
                    'dt4' => (new Schema\SQLiteColumn('Diversity', 'dt4'))
                        ->timestamp()
                        ->nullable(true)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                    'dt5' => (new Schema\SQLiteColumn('Diversity', 'dt5'))
                        ->timestamp()
                        ->nullable(true)
                        ->defaultValue(null),

                    'dti1' => (new Schema\SQLiteColumn('Diversity', 'dti1'))
                        ->timestamp()
                        ->nullable(false),
                    'dti2' => (new Schema\SQLiteColumn('Diversity', 'dti2'))
                        ->timestamp()
                        ->nullable(true),
                    'dti3' => (new Schema\SQLiteColumn('Diversity', 'dti3'))
                        ->timestamp()
                        ->nullable(false)
                        ->defaultValue('2000-01-01 00:00:00.000000'),
                    'dti4' => (new Schema\SQLiteColumn('Diversity', 'dti4'))
                        ->timestamp()
                        ->nullable(true)
                        ->defaultValue(new Fragment('CURRENT_TIMESTAMP')),
                    'dti5' => (new Schema\SQLiteColumn('Diversity', 'dti5'))
                        ->timestamp()
                        ->nullable(true)
                        ->defaultValue(null),

                    'castable_int1' => (new Schema\SQLiteColumn('Diversity', 'castable_int1'))
                        ->integer()
                        ->nullable(false),
                    'castable_int2' => (new Schema\SQLiteColumn('Diversity', 'castable_int2'))
                        ->integer()
                        ->nullable(true),
                    'castable_int3' => (new Schema\SQLiteColumn('Diversity', 'castable_int3'))
                        ->integer()
                        ->nullable(false)
                        ->defaultValue(946684801),
                    'castable_int4' => (new Schema\SQLiteColumn('Diversity', 'castable_int4'))
                        ->integer()
                        ->nullable(true)
                        ->defaultValue(946684802),
                    'castable_int5' => (new Schema\SQLiteColumn('Diversity', 'castable_int5'))
                        ->integer()
                        ->nullable(true)
                        ->defaultValue(null),
                    
                    'castable_str1' => (new Schema\SQLiteColumn('Diversity', 'castable_str1'))
                        ->string()
                        ->nullable(false),
                    'castable_str2' => (new Schema\SQLiteColumn('Diversity', 'castable_str2'))
                        ->string()
                        ->nullable(true),
                    'castable_str3' => (new Schema\SQLiteColumn('Diversity', 'castable_str3'))
                        ->string()
                        ->nullable(false)
                        ->defaultValue('_Hello_'),
                    'castable_str4' => (new Schema\SQLiteColumn('Diversity', 'castable_str4'))
                        ->string()
                        ->nullable(true)
                        ->defaultValue('_World_'),
                    'castable_str5' => (new Schema\SQLiteColumn('Diversity', 'castable_str5'))
                        ->string()
                        ->nullable(true)
                        ->defaultValue(null),
                ],
                'expectedPrimaryKeys' => ['id'],
                'expectedIndexes' => [],
            ],
        ];
    }

    /**
     * @dataProvider generateTable_dataProvider
     * @throws \Exception
     */
    public function test_generateTable(string $class, array $expectedColumns, array $expectedPrimaryKeys, array $expectedIndexes): void
    {
        $reflectionClass = new \ReflectionClass($class);
        $bridge = new CycleBridge($reflectionClass);
        $actual = $bridge->generateCycleTable(self::getDatabaseManager());
        $this->assertEquals($expectedColumns, $actual->getColumns());
        $this->assertEquals($expectedPrimaryKeys, $actual->getPrimaryKeys());
        $this->assertEquals($expectedIndexes, $actual->getIndexes());
    }
}