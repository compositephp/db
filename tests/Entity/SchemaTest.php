<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity;

use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Attributes;
use Composite\DB\Entity\ColumnBuilder;
use Composite\DB\Entity\Schema;
use Composite\DB\Entity\Traits;

final class SchemaTest extends \PHPUnit\Framework\TestCase
{
    public function test_build(): void
    {
        $class = new class extends AbstractEntity {
            use Traits\SoftDelete;

            #[Attributes\PrimaryKey(autoIncrement: true)]
            public readonly int $id;

            public function __construct(
                public string $str = 'abc',
                public int $number = 123,
                private \DateTimeImmutable $dt = new \DateTimeImmutable(),
            ) {}
        };
        $schema = Schema::build($class::class);
        $this->assertCount(4, $schema->columns);
        $this->assertSame($class::class, $schema->class);
        $this->assertNull($schema->table);
        $this->assertTrue($schema->isSoftDelete);
        $this->assertNull($schema->getDatabaseName());
        $this->assertNull($schema->getTableName());
        $this->assertCount(1, $schema->getPrimaryKeyColumns());
        $this->assertSame($schema->getColumn('id'), $schema->getAutoIncrementColumn());
        $this->assertTrue($schema->hasAutoIncrementPrimaryKey());
        $this->assertSame(
            [
                $schema->getColumn('id'),
                $schema->getColumn('deleted_at'),
            ],
            array_values($schema->getNonConstructorColumns())
        );
        $this->assertSame(
            [
                $schema->getColumn('str'),
                $schema->getColumn('number'),
            ],
            array_values($schema->getConstructorColumns())
        );
    }

    public function table_dataProvider(): array
    {
        return [
            [
                'table' => null,
                'expectedDatabaseName' => null,
                'expectedTableName' => null,
            ],
            [
                'table' => new Attributes\Table(db: 'main', name: 'TableName'),
                'expectedDatabaseName' => 'main',
                'expectedTableName' => 'TableName',
            ],
            [
                'table' => new Attributes\Table(db: 'main', ),
                'expectedDatabaseName' => 'main',
                'expectedTableName' => 'Test',
            ],
        ];
    }

    /**
     * @dataProvider table_dataProvider
     */
    public function test_table(?Attributes\Table $table, ?string $expectedDatabaseName, ?string $expectedTableName): void
    {
        $schema = new Schema(
            class: TestStand\TestEntity::class,
            columns: ColumnBuilder::fromReflection(new \ReflectionClass(TestStand\TestEntity::class)),
            table: $table,
            isSoftDelete: false
        );
        $this->assertSame($expectedDatabaseName, $schema->getDatabaseName());
        $this->assertSame($expectedTableName, $schema->getTableName());
    }
}