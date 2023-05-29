<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\Attributes;
use Composite\Entity\AbstractEntity;
use Composite\Entity\Exceptions\EntityException;
use Composite\Entity\Schema;

class TableConfig
{
    private readonly array $entityTraits;

    /**
     * @param class-string<AbstractEntity> $entityClass
     * @param string[] $primaryKeys
     */
    public function __construct(
        public readonly string $connectionName,
        public readonly string $tableName,
        public readonly string $entityClass,
        public readonly array $primaryKeys,
        public readonly ?string $autoIncrementKey = null,
    )
    {
        $this->entityTraits = array_fill_keys(class_uses($entityClass), true);
    }

    /**
     * @throws EntityException
     */
    public static function fromEntitySchema(Schema $schema): TableConfig
    {
        /** @var Attributes\Table|null $tableAttribute */
        $tableAttribute = $schema->getFirstAttributeByClass(Attributes\Table::class);
        if (!$tableAttribute) {
            throw new EntityException(sprintf(
                'Attribute `%s` not found in Entity `%s`',
                Attributes\Table::class, $schema->class
            ));
        }
        $primaryKeys = [];
        $autoIncrementKey = null;

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
        return new TableConfig(
            connectionName: $tableAttribute->connection,
            tableName: $tableAttribute->name,
            entityClass: $schema->class,
            primaryKeys: $primaryKeys,
            autoIncrementKey: $autoIncrementKey,
        );
    }

    public function checkEntity(AbstractEntity $entity): void
    {
        if ($entity::class !== $this->entityClass) {
            throw new EntityException(
                sprintf('Illegal entity `%s` passed to `%s`, only `%s` is allowed',
                    $entity::class,
                    $this::class,
                    $this->entityClass,
                )
            );
        }
    }

    public function hasSoftDelete(): bool
    {
        return !empty($this->entityTraits[Traits\SoftDelete::class]);
    }

    public function hasOptimisticLock(): bool
    {
        return !empty($this->entityTraits[Traits\OptimisticLock::class]);
    }

    public function hasUpdatedAt(): bool
    {
        return !empty($this->entityTraits[Traits\UpdatedAt::class]);
    }
}