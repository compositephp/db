<?php declare(strict_types=1);

namespace Composite\DB\Migrations;

use Composite\DB\Attributes;
use Composite\DB\TableConfig;
use Composite\Entity\AbstractEntity;
use Composite\Entity\Columns;
use Composite\Entity\Schema;
use Composite\DB\Helpers\DateTimeHelper;
use Composite\Entity\Exceptions\EntityException;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Driver\Driver;
use Cycle\Database\Driver\MySQL;
use Cycle\Database\Driver\Postgres;
use Cycle\Database\Driver\SQLite;
use Cycle\Database\Driver\SQLServer;
use Cycle\Database\Schema\AbstractColumn as AbstractCycleColumn;
use Cycle\Database\Schema\AbstractTable as AbstractCycleTable;
use Cycle\Database\Schema\State;

class CycleBridge
{
    /** @psalm-var  non-empty-string $dbName */
    public readonly string $dbName;

    private \ReflectionClass $reflectionClass;
    private Schema $schema;
    private TableConfig $tableConfig;

    /**
     * @throws EntityException
     */
    public function __construct(\ReflectionClass $reflectionClass)
    {
        $this->reflectionClass = $reflectionClass;
        /** @psalm-var class-string $className */
        $className = $reflectionClass->name;
        $this->schema = Schema::build($className);
        $this->tableConfig = TableConfig::fromEntitySchema($this->schema);

        $this->dbName = $this->tableConfig->dbName;
    }

    public function generateCycleTable(DatabaseProviderInterface $databaseProvider): AbstractCycleTable
    {
        $db = $databaseProvider->database($this->dbName);
        /** @var Driver $driver */
        $driver = $db->getDriver();

        $cycleTable = match ($driver::class) {
            MySQL\MySQLDriver::class => new MySQL\Schema\MySQLTable($driver, $this->tableConfig->tableName, ''),
            SQLite\SQLiteDriver::class => new SQLite\Schema\SQLiteTable($driver, $this->tableConfig->tableName, ''),
            Postgres\PostgresDriver::class => new Postgres\Schema\PostgresTable($driver, $this->tableConfig->tableName, ''),
            SQLServer\SQLServerDriver::class => new SQLServer\Schema\SQLServerTable($driver, $this->tableConfig->tableName, ''),
            default => throw new \Exception('Database driver `' . $driver::class . '` is not supported'),
        };

        $newState = new State($this->tableConfig->tableName);
        $newState->setPrimaryKeys($this->getPrimaryKeys());

        foreach ($this->schema->columns as $column) {
            $cycleColumn = $this->getCycleColumn($driver, $column);
            $newState->registerColumn($cycleColumn);
        }
        foreach ($this->getCycleIndexes($driver, $newState) as $cycleIndex) {
            $newState->registerIndex($cycleIndex);
        }
        $cycleTable->setState($newState);
        return $cycleTable;
    }

    private function getCycleColumn(Driver $driver, Columns\AbstractColumn $column): AbstractCycleColumn
    {
        $cycleColumn = match ($driver::class) {
            MySQL\MySQLDriver::class => new MySQL\Schema\MySQLColumn($this->tableConfig->tableName, $column->name),
            SQLite\SQLiteDriver::class => new SQLite\Schema\SQLiteColumn($this->tableConfig->tableName, $column->name),
            Postgres\PostgresDriver::class => new Postgres\Schema\PostgresColumn($this->tableConfig->tableName, $column->name),
            SQLServer\SQLServerDriver::class => new SQLServer\Schema\SQLServerColumn($this->tableConfig->tableName, $column->name),
            default => throw new \Exception('Database driver `' . $driver::class . '` is not supported'),
        };
        $cycleColumn->nullable($column->isNullable);

        if ($column instanceof Columns\CastableColumn) {
            /** @psalm-var class-string $classString */
            $classString = $column->type;
            $castableReflectionClass = new \ReflectionClass($classString);
            $returnType = $castableReflectionClass->getMethod('uncast')->getReturnType();
            $isString = false;
            if ($returnType) {
                if ($returnType instanceof \ReflectionUnionType) {
                    foreach ($returnType->getTypes() as $namedType) {
                        if ($namedType->getName() !== 'int') {
                            $isString = true;
                        }
                    }
                } elseif ($returnType instanceof \ReflectionNamedType) {
                    $isString = $returnType->getName() !== 'int';
                } else {
                    $isString = true;
                }
            } else {
                $isString = true;
            }
            if ($isString) {
                $cycleColumn->string($this->getMigrationSize($column));
            } else {
                $cycleColumn->integer();
            }
        } elseif ($column instanceof Columns\StringColumn) {
            $cycleColumn->string($this->getMigrationSize($column));
        } elseif ($column instanceof Columns\IntegerColumn) {
            if ($this->tableConfig->autoIncrementKey === $column->name) {
                $cycleColumn->primary();
            } else {
                $cycleColumn->integer();
            }
        } elseif ($column instanceof Columns\FloatColumn) {
            if ($decimalConfig = $this->getMigrationPrecisionScale($column)) {
                $cycleColumn->decimal($decimalConfig[0], $decimalConfig[1]);
            } else {
                $cycleColumn->float();
            }
        } elseif ($column instanceof Columns\BoolColumn) {
            $cycleColumn->boolean();
        } elseif ($column instanceof Columns\DateTimeColumn) {
            $cycleColumn->timestamp();
        } elseif ($column instanceof Columns\ArrayColumn || $column instanceof Columns\EntityColumn || $column instanceof Columns\ObjectColumn) {
            $cycleColumn->json();
        } elseif ($column instanceof Columns\UnitEnumColumn) {
            /** @var \UnitEnum $enumClass */
            $enumClass = $column->type;
            $cycleColumn->enum(
                array_map(
                    fn (\UnitEnum $enum) => $enum->name,
                    $enumClass::cases()
                )
            );
        } elseif ($column instanceof Columns\BackedEnumColumn) {
            /** @var \BackedEnum $enumClass */
            $enumClass = $column->type;
            $reflectionEnum = new \ReflectionEnum($column->type);
            /** @var \ReflectionNamedType $backingType */
            $backingType = $reflectionEnum->getBackingType();
            if ($backingType->getName() === 'int') {
                $cycleColumn->integer();
            } else {
                $cycleColumn->enum(
                    array_map(
                        fn (\BackedEnum $enum) => $enum->value,
                        $enumClass::cases()
                    )
                );
            }
        } else {
            throw new \Exception('Unsupported column ' . $column::class);
        }
        $defaultValue = $this->getMigrationDefaultValue($column);
        if ($defaultValue !== null) {
            $cycleColumn->defaultValue($defaultValue);
        }
        return $cycleColumn;
    }

    private function getMigrationDefaultValue(Columns\AbstractColumn $column): string|int|float|bool|null
    {
        $columnAttribute = $this->getColumnAttribute($column);
        if ($columnAttribute && $columnAttribute->default) {
            return $columnAttribute->default;
        }
        $defaultValue = null;
        $defaultValueDefined = false;
        foreach ($this->reflectionClass->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->getName() === $column->name && $parameter->isPromoted() && $parameter->isDefaultValueAvailable()) {
                $defaultValue = $parameter->getDefaultValue();
                $defaultValueDefined = true;
            }
        }
        if (!$defaultValueDefined) {
            foreach ($this->reflectionClass->getProperties() as $property) {
                if ($property->name === $column->name && $property->hasDefaultValue()) {
                    $defaultValue = $property->getDefaultValue();
                    $defaultValueDefined = true;
                }
            }
        }
        if (!$defaultValueDefined || $defaultValue === null) {
            return null;
        }
        if (is_string($defaultValue) || is_numeric($defaultValue) || is_bool($defaultValue)) {
            return $defaultValue;
        }
        if (is_array($defaultValue)) {
            return json_encode($defaultValue);
        }
        if ($column instanceof Columns\CastableColumn) {
            return $column->uncast($defaultValue);
        }
        if ($defaultValue instanceof \BackedEnum) {
            return $defaultValue->value;
        }
        if ($defaultValue instanceof \UnitEnum) {
            return $defaultValue->name;
        }
        if ($defaultValue instanceof \stdClass) {
            return json_encode($defaultValue);
        }
        if ($defaultValue instanceof AbstractEntity) {
            return json_encode($defaultValue);
        }
        if ($defaultValue instanceof \DateTimeInterface) {
            $unixTime = intval($defaultValue->format('U'));
            $now = time();
            if ($unixTime === $now || $unixTime === $now - 1) {
                return AbstractCycleColumn::DATETIME_NOW;
            }
            return DateTimeHelper::dateTimeToString($defaultValue);
        }
        return null;
    }

    private function getMigrationSize(Columns\AbstractColumn $column): int
    {
        $columnAttribute = $this->getColumnAttribute($column);
        return $columnAttribute?->size ?? Config::DEFAULT_STRING_SIZE;
    }

    private function getMigrationPrecisionScale(Columns\AbstractColumn $column): ?array
    {
        $columnAttribute = $this->getColumnAttribute($column);
        if (!$columnAttribute || !$columnAttribute->precision) {
            return null;
        }
        return [$columnAttribute->precision, $columnAttribute->scale ?? 0];
    }

    private function getColumnAttribute(Columns\AbstractColumn $column): ?\Composite\DB\Attributes\Column
    {
        $reflectionProperty = $this->reflectionClass->getProperty($column->name);
        $attributes = $reflectionProperty->getAttributes(\Composite\DB\Attributes\Column::class);
        if ($attributes) {
            /** @var \Composite\DB\Attributes\Column $attributeInstance */
            $attributeInstance = $attributes[0]->newInstance();
            return $attributeInstance;
        }
        return null;
    }

    /**
     * @return string[]
     */
    private function getPrimaryKeys(): array
    {
        $primaryKeys = $this->tableConfig->primaryKeys;
        if (!$this->tableConfig->autoIncrementKey && $this->tableConfig->isSoftDelete) {
            $primaryKeys[] = 'deleted_at';
        }
        return array_unique($primaryKeys);
    }

    /**
     * @return \Cycle\Database\Schema\AbstractIndex[]
     */
    private function getCycleIndexes(Driver $driver, State $state): array
    {
        $indexes = array_map(
            fn (\ReflectionAttribute $attribute) => $attribute->newInstance(),
            $this->reflectionClass->getAttributes(\Composite\DB\Attributes\Index::class)
        );
        $result = [];
        /** @var \Composite\DB\Attributes\Index $index */
        foreach ($indexes as $index) {
            $indexName = $index->name ?: $index->generateName($state->getName());
            $cycleIndex = match ($driver::class) {
                MySQL\MySQLDriver::class => new MySQL\Schema\MySQLIndex($state->getName(), $indexName),
                SQLite\SQLiteDriver::class => new SQLite\Schema\SQLiteIndex($state->getName(), $indexName),
                Postgres\PostgresDriver::class => new Postgres\Schema\PostgresIndex($state->getName(), $indexName),
                SQLServer\SQLServerDriver::class => new SQLServer\Schema\SQLServerIndex($state->getName(), $indexName),
                default => throw new \Exception('Database driver `' . $driver::class . '` is not supported'),
            };
            $result[] = $cycleIndex
                ->columns($index->columns)
                ->unique($index->isUnique)
                ->sort($index->sort);
        }
        return $result;
    }
}