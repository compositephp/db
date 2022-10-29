<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema\Parsers;

use Composite\DB\Generator\Schema\ColumnType;
use Composite\DB\Generator\Schema\SQLColumn;
use Composite\DB\Generator\Schema\SQLEnum;
use Composite\DB\Generator\Schema\SQLIndex;
use Composite\DB\Generator\Schema\SQLSchema;

class SQLiteSchemaParser
{
    public const TABLE_SQL = "SELECT sql FROM sqlite_schema WHERE name = :tableName";
    public const INDEXES_SQL = "SELECT sql FROM sqlite_master WHERE type = 'index' and tbl_name = :tableName";

    private const TABLE_NAME_PATTERN = '/^create table (?:`|\"|\')?(\w+)(?:`|\"|\')?/i';
    private const COLUMN_PATTERN = '/^(?!constraint|primary key)(?:`|\"|\')?(\w+)(?:`|\"|\')? ([a-zA-Z]+)\s?(\(([\d,\s]+)\))?/i';
    private const CONSTRAINT_PATTERN = '/^(?:constraint) (?:`|\"|\')?\w+(?:`|\"|\')? primary key \(([\w\s,\'\"`]+)\)/i';
    private const PRIMARY_KEY_PATTERN = '/^primary key \(([\w\s,\'\"`]+)\)/i';
    private const ENUM_PATTERN = '/check \((?:`|\"|\')?(\w+)(?:`|\"|\')? in \((.+)\)\)/i';

    public function __construct(
        private readonly string $tableSql,
        private readonly array $indexesSql,
    ) {}

    public function getSchema(): SQLSchema
    {
        $columns = $enums = $primaryKeys = [];
        $columnsStarted = false;
        $tableName = '';
        $lines = array_map(
            fn ($line) => trim(preg_replace("/\s+/", " ", $line)),
            explode("\n", $this->tableSql),
        );
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (!$line) {
                continue;
            }
            if (!$tableName && preg_match(self::TABLE_NAME_PATTERN, $line, $matches)) {
                $tableName = $matches[1];
            }
            if (!$columnsStarted) {
                if (str_starts_with($line, '(') || str_ends_with($line, '(')) {
                    $columnsStarted = true;
                }
                continue;
            }
            if ($line === ')') {
                break;
            }
            if (!str_ends_with($line, ',')) {
                if (!empty($lines[$i + 1]) && !str_starts_with($lines[$i + 1], ')')) {
                    $lines[$i + 1] = $line . ' ' . $lines[$i + 1];
                    continue;
                }
            }
            if ($column = $this->parseSQLColumn($line)) {
                $columns[$column->name] = $column;
            }
            $primaryKeys = array_merge($primaryKeys, $this->parsePrimaryKeys($line));
            if ($enum = $this->parseEnum($line)) {
                $enums[$column?->name ?? $enum->name] = $enum;
            }
        }
        return new SQLSchema(
            tableName: $tableName,
            columns: $columns,
            enums: $enums,
            primaryKeys: array_unique($primaryKeys),
            indexes: $this->getIndexes(),
        );
    }

    private function parseSQLColumn(string $sqlLine): ?SQLColumn
    {
        if (!preg_match(self::COLUMN_PATTERN, $sqlLine, $matches)) {
            return null;
        }
        $name = $matches[1];
        $rawType = $matches[2];
        $rawTypeParams = !empty($matches[4]) ? str_replace(' ', '', $matches[4]) : null;
        $type = $this->getColumnType($rawType) ?? ColumnType::String;
        $hasDefaultValue = stripos($sqlLine, ' default ') !== false;
        return new SQLColumn(
            name: $name,
            sql: $sqlLine,
            type: $type,
            size: $this->getColumnSize($type, $rawTypeParams),
            precision: $this->getColumnPrecision($type, $rawTypeParams),
            scale: $this->getScale($type, $rawTypeParams),
            isNullable: stripos($sqlLine, ' not null') === false,
            hasDefaultValue: $hasDefaultValue,
            defaultValue: $hasDefaultValue ? $this->getDefaultValue($sqlLine) : null,
            isAutoincrement: stripos($sqlLine, ' autoincrement') !== false,
        );
    }

    private function getColumnType(string $rawType): ?ColumnType
    {
        if (!preg_match('/^([a-zA-Z]+).*/', $rawType, $matches)) {
            return null;
        }
        $type = strtolower($matches[1]);
        return match ($type) {
            'integer', 'int' => ColumnType::Integer,
            'real' => ColumnType::Float,
            'timestamp' => ColumnType::Datetime,
            'enum' => ColumnType::Enum,
            default => ColumnType::String,
        };
    }

    private function getColumnSize(ColumnType $type, ?string $typeParams): ?int
    {
        if ($type !== ColumnType::String || !$typeParams) {
            return null;
        }
        return (int)$typeParams;
    }

    private function getColumnPrecision(ColumnType $type, ?string $typeParams): ?int
    {
        if ($type !== ColumnType::Float || !$typeParams) {
            return null;
        }
        $parts = explode(',', $typeParams);
        return (int)$parts[0];
    }

    private function getScale(ColumnType $type, ?string $typeParams): ?int
    {
        if ($type !== ColumnType::Float || !$typeParams) {
            return null;
        }
        $parts = explode(',', $typeParams);
        return !empty($parts[1]) ? (int)$parts[1] : null;
    }

    private function getDefaultValue(string $sqlLine): mixed
    {
        $sqlLine = $this->cleanCheckEnum($sqlLine);
        if (preg_match('/default\s+\'(.*)\'/iu', $sqlLine, $matches)) {
            return $matches[1];
        } elseif (preg_match('/default\s+([\w.]+)/iu', $sqlLine, $matches)) {
            $defaultValue = $matches[1];
            if (strtolower($defaultValue) === 'null') {
                return null;
            }
            return $defaultValue;
        }
        return null;
    }

    private function parsePrimaryKeys(string $sqlLine): array
    {
        if (preg_match(self::COLUMN_PATTERN, $sqlLine, $matches)) {
            $name = $matches[1];
            return stripos($sqlLine, ' primary key') !== false ? [$name] : [];
        }
        if (!preg_match(self::CONSTRAINT_PATTERN, $sqlLine, $matches)
            && !preg_match(self::PRIMARY_KEY_PATTERN, $sqlLine, $matches)) {
            return [];
        }
        $primaryColumnsRaw = $matches[1];
        $primaryColumnsRaw = str_replace(['\'', '"', '`', ' '], '', $primaryColumnsRaw);
        return explode(',', $primaryColumnsRaw);
    }

    private function parseEnum(string $sqlLine): ?SQLEnum
    {
        if (!preg_match(self::ENUM_PATTERN, $sqlLine, $matches)) {
            return null;
        }
        $name = $matches[1];
        $values = [];
        $sqlValues = array_map('trim', explode(',', $matches[2]));
        foreach ($sqlValues as $value) {
            $value = trim($value);
            if (str_starts_with($value, '\'')) {
                $value = trim($value, '\'');
            } elseif (str_starts_with($value, '"')) {
                $value = trim($value, '"');
            }
            $values[] = $value;
        }
        return new SQLEnum(name: $name, values: $values);
    }

    /**
     * @return SQLIndex[]
     */
    private function getIndexes(): array
    {
        $result = [];
        foreach ($this->indexesSql as $indexSql) {
            $indexSql = trim(str_replace("\n", " ", $indexSql));
            $indexSql = preg_replace("/\s+/", " ", $indexSql);
            if (!preg_match('/index(?:\s+)(?:`|\"|\')?(\w+)(?:`|\"|\')?/i', $indexSql, $nameMatch)) {
                continue;
            }
            $name = $nameMatch[1];
            if (!preg_match('/\(([`"\',\s\w]+)\)/', $indexSql, $columnsMatch)) {
                continue;
            }
            $columnsRaw = array_map(
                fn (string $column) => str_replace(['`', '\'', '"'], '', trim($column)),
                explode(',', $columnsMatch[1])
            );
            $columns = $sort = [];
            foreach ($columnsRaw as $columnRaw) {
                $parts = explode(' ', $columnRaw);
                $columns[] = $parts[0];
                if (!empty($parts[1])) {
                    $sort[$parts[0]] = strtolower($parts[1]);
                }
            }
            $result[] = new SQLIndex(
                name: $name,
                isUnique: stripos($indexSql, ' unique index ') !== false,
                columns: $columns,
                sort: $sort,
            );
        }
        return $result;
    }

    private function cleanCheckEnum(string $sqlLine): string
    {
        return preg_replace('/ check \(\"\w+\" IN \(.+\)\)/i', '', $sqlLine);
    }
}