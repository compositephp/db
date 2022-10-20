<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

use Composite\DB\Entity\Attributes;

abstract class AbstractColumn
{
    public function __construct(
        /** @psalm-var non-empty-string $name */
        public readonly string $name,
        public readonly string $type,
        public readonly bool $hasDefaultValue,
        public readonly mixed $defaultValue,
        public readonly bool $isNullable,
        public readonly bool $isReadOnly,
        public readonly bool $isConstructorPromoted,
        public readonly bool $isStrict = false,
        public readonly ?Attributes\PrimaryKey $primaryKey = null,
    ) {}

    /**
     * @param mixed $dbValue value from your database
     * @return mixed value for your Entity, null if impossible to cast
     */
    abstract public function cast(mixed $dbValue): mixed;

    /**
     * @param mixed $entityValue value from your Entity
     * @return string|int|float|bool|null value for your database, null if impossible to uncast
     */
    abstract public function uncast(mixed $entityValue): string|int|float|bool|null;

    public function isAutoIncrement(): bool
    {
        return $this->primaryKey?->autoIncrement ?? false;
    }

    public function hasDefaultValue(): bool
    {
        //TODO
        return false;
    }
}