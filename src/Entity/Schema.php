<?php declare(strict_types=1);

namespace Composite\DB\Entity;

use Composite\DB\Entity\Columns\AbstractColumn;
use Composite\DB\Entity\Traits;
use Composite\DB\Exceptions\EntityException;

class Schema
{
    /**
     * @param $columns AbstractColumn[]
     */
    public function __construct(
        public readonly string $class,
        public readonly array $columns,
        public readonly ?Attributes\Table $table,
        public readonly bool $isSoftDelete,
    ) {}

    /**
     * @throws EntityException
     */
    public static function build(string $class): self
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $exception) {
            throw EntityException::fromThrowable($exception);
        }
        $columns = ColumnBuilder::fromReflection($reflection);
        $tableAttribute = null;
        foreach ($reflection->getAttributes() as $reflectionAttribute) {
            if ($reflectionAttribute->getName() === Attributes\Table::class) {
                /** @var Attributes\Table $tableAttribute */
                $tableAttribute = $reflectionAttribute->newInstance();
            }
        }
        return new self(
            class: $class,
            columns: $columns,
            table: $tableAttribute,
            isSoftDelete: \in_array(Traits\SoftDelete::class, $reflection->getTraitNames()),
        );
    }

    /**
     * @param array $data
     * @return array
     * @throws EntityException
     */
    public function castData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if ($column = $this->columns[$key] ?? null) {
                if ($value === null && $column->isNullable) {
                    continue;
                }
                try {
                    $data[$key] = $column->cast($value);
                } catch (\Throwable $throwable) {
                    if (!$column->isStrict) {
                        if ($column->hasDefaultValue()) {
                            unset($data[$key]);
                            continue;
                        } elseif ($column->isNullable) {
                            $data[$key] = null;
                            continue;
                        }
                    }
                    throw EntityException::fromThrowable($throwable);
                }
            }
        }
        return $data;
    }

    /**
     * @return AbstractColumn[]
     */
    public function getConstructorColumns(): array
    {
        return array_filter($this->columns, fn(AbstractColumn $column) => $column->isConstructorPromoted);
    }

    /**
     * @return AbstractColumn[]
     */
    public function getNonConstructorColumns(): array
    {
        return array_filter($this->columns, fn(AbstractColumn $column) => !$column->isConstructorPromoted);
    }

    /**
     * @return AbstractColumn[]
     */
    public function getPrimaryKeyColumns(): array
    {
        return array_filter($this->columns, fn(AbstractColumn $column) => $column->primaryKey !== null);
    }

    public function getAutoIncrementColumn(): ?AbstractColumn
    {
        foreach ($this->getPrimaryKeyColumns() as $column) {
            if ($column->primaryKey->autoIncrement ?? false) {
                return $column;
            }
        }
        return null;
    }

    public function hasAutoIncrementPrimaryKey(): bool
    {
        return (bool)array_filter($this->getPrimaryKeyColumns(), fn(AbstractColumn $column) => $column->isAutoIncrement());
    }

    public function getDatabaseName(): ?string
    {
        return $this->table?->db;
    }

    public function getTableName(): ?string
    {
        if (!$this->table) {
            return null;
        }
        if ($this->table->name) {
            return $this->table->name;
        }
        if (!preg_match('/(\w+)(Entity|Model)$|\w+$/', $this->class, $matches)) {
            return null;
        }
        return $matches[1] ?? $matches[0];
    }
}
