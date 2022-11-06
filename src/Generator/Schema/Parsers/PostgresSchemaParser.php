<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema\Parsers;

use Composite\DB\Generator\Schema\ColumnType;
use Composite\DB\Generator\Schema\SQLColumn;
use Composite\DB\Generator\Schema\SQLEnum;
use Composite\DB\Generator\Schema\SQLIndex;
use Composite\DB\Generator\Schema\SQLSchema;
use Doctrine\DBAL\Connection;

class PostgresSchemaParser
{
    public const COLUMNS_SQL = "
        SELECT * FROM information_schema.columns 
        WHERE table_schema = 'public' AND table_name = :tableName;
     ";

    public const INDEXES_SQL = "
        SELECT * FROM pg_indexes
        WHERE schemaname = 'public' AND tablename = :tableName;
     ";

    public const PRIMARY_KEY_SQL = <<<SQL
        SELECT a.attname as column_name
        FROM pg_index i
        JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY (i.indkey)
        WHERE i.indrelid = '":tableName"'::regclass AND i.indisprimary;
     SQL;

    public const ALL_ENUMS_SQL = "
        SELECT t.typname as enum_name, e.enumlabel as enum_value
        FROM pg_type t
        JOIN pg_enum e ON t.oid = e.enumtypid
        JOIN pg_catalog.pg_namespace n ON n.oid = t.typnamespace
        WHERE n.nspname = 'public';
    ";

    private readonly string $tableName;
    private readonly array $informationSchemaColumns;
    private readonly array $informationSchemaIndexes;
    private readonly array $primaryKeys;
    private readonly array $allEnums;

    public static function getPrimaryKeySQL(string $tableName): string
    {
        return "
            SELECT a.attname as column_name
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY (i.indkey)
            WHERE i.indrelid = '\"" . $tableName . "\"'::regclass AND i.indisprimary;
        ";
    }

    public function __construct(Connection $connection, string $tableName) {
        $this->tableName = $tableName;
        $this->informationSchemaColumns = $connection->executeQuery(
            sql: PostgresSchemaParser::COLUMNS_SQL,
            params: ['tableName' => $tableName],
        )->fetchAllAssociative();
        $this->informationSchemaIndexes = $connection->executeQuery(
            sql: PostgresSchemaParser::INDEXES_SQL,
            params: ['tableName' => $tableName],
        )->fetchAllAssociative();

        if ($primaryKeySQL = PostgresSchemaParser::getPrimaryKeySQL($tableName)) {
            $this->primaryKeys = array_map(
                fn(array $row): string => $row['column_name'],
                $connection->executeQuery($primaryKeySQL)->fetchAllAssociative()
            );
        } else {
            $this->primaryKeys = [];
        }

        $allEnumsRaw = $connection->executeQuery(PostgresSchemaParser::ALL_ENUMS_SQL)->fetchAllAssociative();
        $allEnums = [];
        foreach ($allEnumsRaw as $enumRaw) {
            $name = $enumRaw['enum_name'];
            $value = $enumRaw['enum_value'];
            if (!isset($allEnums[$name])) {
                $allEnums[$name] = [];
            }
            $allEnums[$name][] = $value;
        }
        $this->allEnums = $allEnums;
    }

    public function getSchema(): SQLSchema
    {
        $columns = $enums = [];
        foreach ($this->informationSchemaColumns as $informationSchemaColumn) {
            $name = $informationSchemaColumn['column_name'];
            $type = $this->getType($informationSchemaColumn);
            $sqlDefault = $informationSchemaColumn['column_default'];
            $isNullable = $informationSchemaColumn['is_nullable'] === 'YES';
            $defaultValue = $this->getDefaultValue($type, $sqlDefault);
            $hasDefaultValue = $defaultValue !== null || $isNullable;
            $isAutoincrement = $sqlDefault && str_starts_with($sqlDefault, 'nextval(');

            if ($type === ColumnType::Enum) {
                $udtName = $informationSchemaColumn['udt_name'];
                $enums[$name] = new SQLEnum(name: $udtName, values: $this->allEnums[$udtName]);
            }
            $column = new SQLColumn(
                name: $name,
                sql: $informationSchemaColumn['udt_name'],
                type: $type,
                size: $this->getSize($type, $informationSchemaColumn),
                precision: $this->getPrecision($type, $informationSchemaColumn),
                scale: $this->getScale($type, $informationSchemaColumn),
                isNullable: $isNullable,
                hasDefaultValue: $hasDefaultValue,
                defaultValue: $defaultValue,
                isAutoincrement: $isAutoincrement,
            );
            $columns[$column->name] = $column;
        }
        return new SQLSchema(
            tableName: $this->tableName,
            columns: $columns,
            enums: $enums,
            primaryKeys: $this->primaryKeys,
            indexes: $this->parseIndexes(),
        );
    }

    private function getType(array $informationSchemaColumn): ColumnType
    {
        $dataType = $informationSchemaColumn['data_type'];
        $udtName = $informationSchemaColumn['udt_name'];
        if ($dataType === 'USER-DEFINED' && !empty($this->allEnums[$udtName])) {
            return ColumnType::Enum;
        }
        if (preg_match('/^int(\d?)$/', $udtName)) {
            return ColumnType::Integer;
        }
        if (preg_match('/^float(\d?)$/', $udtName)) {
            return ColumnType::Float;
        }
        $matchType = match ($udtName) {
            'numeric' => ColumnType::Float,
            'timestamp', 'timestamptz' => ColumnType::Datetime,
            'json', 'array' => ColumnType::Array,
            'bool' => ColumnType::Boolean,
            default => null,
        };
        return $matchType ?? ColumnType::String;
    }

    private function getSize(ColumnType $type, array $informationSchemaColumn): ?int
    {
        if ($type === ColumnType::String) {
            return $informationSchemaColumn['character_maximum_length'];
        }
        return null;
    }

    private function getPrecision(ColumnType $type, array $informationSchemaColumn): ?int
    {
        if ($type !== ColumnType::Float) {
            return null;
        }
        return $informationSchemaColumn['numeric_precision'];
    }

    private function getScale(ColumnType $type, array $informationSchemaColumn): ?int
    {
        if ($type !== ColumnType::Float) {
            return null;
        }
        return $informationSchemaColumn['numeric_scale'];
    }

    private function getDefaultValue(ColumnType $type, ?string $sqlValue): mixed
    {
        if ($sqlValue === null || strcasecmp($sqlValue, 'null') === 0) {
            return  null;
        }
        if (str_starts_with($sqlValue, 'nextval(')) {
            return null;
        }
        $parts = explode('::', $sqlValue);
        return trim($parts[0], '\'');
    }

    private function parseIndexes(): array
    {
        $result = [];
        foreach ($this->informationSchemaIndexes as $informationSchemaIndex) {
            $name = $informationSchemaIndex['indexname'];
            $sql = $informationSchemaIndex['indexdef'];
            $isUnique = stripos($sql, ' unique index ') !== false;

            if (!preg_match('/\(([`"\',\s\w]+)\)/', $sql, $columnsMatch)) {
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
                    $sort[$parts[0]] = strtoupper($parts[1]);
                }
            }
            if ($columns === $this->primaryKeys) {
                continue;
            }
            $result[] = new SQLIndex(
                name: $name,
                isUnique: $isUnique,
                columns: $columns,
            );
        }
        return $result;
    }
}