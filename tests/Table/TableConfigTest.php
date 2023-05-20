<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Composite\DB\Attributes;
use Composite\DB\TableConfig;
use Composite\DB\Traits;
use Composite\Entity\AbstractEntity;
use Composite\Entity\Schema;

final class TableConfigTest extends \PHPUnit\Framework\TestCase
{
    public function test_build(): void
    {
        $class = new
            #[Attributes\Table(connection: 'sqlite', name: 'Foo')]
            class extends AbstractEntity {
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
        $tableConfig = TableConfig::fromEntitySchema($schema);
        $this->assertNotEmpty($tableConfig->connectionName);
        $this->assertNotEmpty($tableConfig->tableName);
        $this->assertTrue($tableConfig->hasSoftDelete());
        $this->assertCount(1, $tableConfig->primaryKeys);
        $this->assertSame('id', $tableConfig->autoIncrementKey);
    }
}