<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema;

use Composite\DB\Migrations\Config;

class SQLColumn
{
    public function __construct(
        public readonly string $name,
        public readonly string|array $sql,
        public readonly ColumnType $type,
        public readonly ?int $size,
        public readonly ?int $precision,
        public readonly ?int $scale,
        public readonly bool $isNullable,
        public readonly bool $hasDefaultValue,
        public readonly mixed $defaultValue,
        public readonly bool $isAutoincrement,
    ) {}

    public function sizeIsDefault(): bool
    {
        if ($this->type !== ColumnType::String) {
            return true;
        }
        if ($this->size === null) {
            return true;
        }
        return $this->size === Config::DEFAULT_STRING_SIZE;
    }

    public function getColumnAttributeProperties(): array
    {
        $result = [];
        if (!$this->sizeIsDefault()) {
            $result[] = 'size: ' . $this->size;
        }
        if ($this->precision) {
            $result[] = 'precision: ' . $this->precision;
        }
        if ($this->scale) {
            $result[] = 'scale: ' . $this->scale;
        }
        return $result;
    }
}