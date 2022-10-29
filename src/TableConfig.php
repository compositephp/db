<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\Attributes;
use Composite\Entity\Exceptions\EntityException;
use Composite\Entity\Schema;

class TableConfig
{
    public function __construct(
        public readonly string $dbName,
        public readonly string $tableName,
        public readonly string $entityClass,
        public readonly array $primaryKeys,
        public readonly ?string $autoIncrementKey = null,
        public readonly bool $isSoftDelete = false,
        public readonly bool $isOptimisticLock = false,
    )
    {}

    /**
     * @throws EntityException
     */
    public static function fromEntitySchema(Schema $schema): TableConfig
    {
        /** @var Attributes\Table|null $tableAttribute */
        $tableAttribute = null;
        foreach ($schema->attributes as $attribute) {
            if ($attribute instanceof Attributes\Table) {
                $tableAttribute = $attribute;
            }
        }
        if (!$tableAttribute) {
            throw new EntityException(sprintf(
                'Attribute `%s` not found in Entity `%s`',
                Attributes\Table::class, $schema->class
            ));
        }
        $primaryKeys = [];
        $autoIncrementKey = null;
        $isSoftDelete = $isOptimisticLock = false;

        foreach ($schema->columns as $column) {
            foreach ($column->attributes as $attribute) {
                if ($attribute instanceof Attributes\PrimaryKey) {
                    $primaryKeys[] = $column->name;
                    if ($attribute->autoIncrement) {
                        $autoIncrementKey = $column->name;
                    }
                }
            }
        }
        foreach (class_uses($schema->class) as $traitClass) {
            if ($traitClass === Traits\SoftDelete::class) {
                $isSoftDelete = true;
            } elseif ($traitClass === Traits\OptimisticLock::class) {
                $isOptimisticLock = true;
            }
        }
        return new TableConfig(
            dbName: $tableAttribute->db,
            tableName: $tableAttribute->name,
            entityClass: $schema->class,
            primaryKeys: $primaryKeys,
            autoIncrementKey: $autoIncrementKey,
            isSoftDelete: $isSoftDelete,
            isOptimisticLock: $isOptimisticLock,
        );
    }
}