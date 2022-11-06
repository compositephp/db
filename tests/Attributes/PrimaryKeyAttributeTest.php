<?php declare(strict_types=1);

namespace Composite\DB\Tests\Attributes;

use Composite\DB\TableConfig;
use Composite\Entity\AbstractEntity;
use Composite\DB\Attributes;

final class PrimaryKeyAttributeTest extends \PHPUnit\Framework\TestCase
{
    public function primaryKey_dataProvider(): array
    {
        return [
            [
                'entity' => new
                #[Attributes\Table(connection: 'sqlite', name: 'Foo')]
                class extends AbstractEntity {
                    #[Attributes\PrimaryKey(autoIncrement: true)]
                    public readonly int $id;
                    public string $bar2;

                    public function __construct(
                        public int $foo1 = 1,
                        public string $bar1 = 'bar',
                    ) {}
                },
                'expected' => [
                    'id' => new Attributes\PrimaryKey(autoIncrement: true),
                    'foo1' => null,
                    'bar1' => null,
                    'bar2' => null,
                ]
            ],
        ];
    }

    /**
     * @dataProvider primaryKey_dataProvider
     */
    public function test_primaryKey(AbstractEntity $entity, array $expected): void
    {
        $schema = $entity::schema();
        $tableConfig = TableConfig::fromEntitySchema($schema);
        /**
         * @var Attributes\PrimaryKey|null $expectedPrimaryKey
         */
        foreach ($expected as $name => $expectedPrimaryKey) {
            $this->assertNotNull($schema->getColumn($name));
            if ($expectedPrimaryKey) {
                $this->assertContains($name, $tableConfig->primaryKeys);
                if ($expectedPrimaryKey->autoIncrement) {
                    $this->assertSame($name, $tableConfig->autoIncrementKey);
                }
            }
        }
    }
}