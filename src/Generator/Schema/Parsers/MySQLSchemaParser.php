<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema\Parsers;

use Composite\DB\Generator\Schema\ColumnType;
use Composite\DB\Generator\Schema\SQLColumn;
use Composite\DB\Generator\Schema\SQLEnum;
use Composite\DB\Generator\Schema\SQLIndex;
use Composite\DB\Generator\Schema\SQLSchema;
use iamcal\SQLParser;

class MySQLSchemaParser
{
    public function __construct(
        private readonly string $sql,
    ) {}

    public function getSchema(): SQLSchema
    {
        $columns = $enums = $primaryKeys = $indexes = [];
        $parser = new SQLParser();
        $tokens = $parser->parse($this->sql);
        $table = current($tokens);
        $tableName = $table['name'];

        foreach ($table['fields'] as $field) {
            $name = $field['name'];
            $precision = $scale = null;
            $sqlType = $field['type'];
            $size = !empty($field['length']) ? (int)$field['length'] : null;
            $type = $this->getType($sqlType, $size);

            if ($type === ColumnType::Enum) {
                $enums[$name] = new SQLEnum(name: $name, values: $field['values']);
            } elseif ($type === ColumnType::Float) {
                $precision = $size;
                $scale = !empty($field['decimals']) ? (int)$field['decimals'] : null;
                $size = null;
            }
            if (isset($field['default'])) {
                $hasDefaultValue = true;
                $defaultValue = $this->getDefaultValue($type, $field['default']);
            } else {
                $hasDefaultValue = false;
                $defaultValue = null;
            }
            $column = new SQLColumn(
                name: $name,
                sql: $sqlType,
                type: $type,
                size: $size,
                precision: $precision,
                scale: $scale,
                isNullable: !empty($field['null']),
                hasDefaultValue: $hasDefaultValue,
                defaultValue: $defaultValue,
                isAutoincrement: !empty($field['auto_increment']),
            );
            $columns[$column->name] = $column;
        }
        foreach ($table['indexes'] as $index) {
            $indexType = strtolower($index['type']);
            $cols = $sort = [];
            foreach ($index['cols'] as $col) {
                $colName = $col['name'];
                $cols[] = $colName;
                if (!empty($col['direction'])) {
                    $sort[$colName] = $col['direction'];
                }
            }
            if ($indexType === 'primary') {
                $primaryKeys = $cols;
                continue;
            }
            $indexes[] = new SQLIndex(
                name: $index['name'] ?? null,
                isUnique: $indexType === 'unique',
                columns: $cols,
                sort: $sort,
            );
        }
        return new SQLSchema(
            tableName: $tableName,
            columns: $columns,
            enums: $enums,
            primaryKeys: array_unique($primaryKeys),
            indexes: $indexes,
        );
    }

    private function getType(string $type, ?int $size): ColumnType
    {
        $type = strtolower($type);
        if ($type === 'tinyint' && $size === 1) {
            return ColumnType::Boolean;
        }
        return match ($type) {
            'integer', 'int', 'smallint', 'tinyint', 'mediumint', 'bigint' => ColumnType::Integer,
            'float', 'double', 'numeric', 'decimal' => ColumnType::Float,
            'timestamp', 'datetime' => ColumnType::Datetime,
            'json', 'set' => ColumnType::Array,
            'enum' => ColumnType::Enum,
            default => ColumnType::String,
        };
    }

    private function getDefaultValue(ColumnType $type, mixed $value): mixed
    {
        if ($value === null || (is_string($value) && strcasecmp($value, 'null') === 0)) {
            return  null;
        }
        return $value;
    }
}