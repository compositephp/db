<?php declare(strict_types=1);

namespace Composite\DB\MultiQuery;

class MultiInsert
{
    public readonly string $sql;
    public readonly array $parameters;

    public function __construct(string $tableName, array $rows) {
        if (!$rows) {
            $this->sql = '';
            $this->parameters = [];
            return;
        }
        $firstRow = reset($rows);
        $columnNames = array_map(fn ($columnName) => "`$columnName`", array_keys($firstRow));
        $sql = "INSERT INTO `$tableName` (" . implode(', ', $columnNames) . ") VALUES ";
        $valuesSql = $parameters = [];

        $index = 0;
        foreach ($rows as $row) {
            $valuePlaceholder = [];
            foreach ($row as $column => $value) {
                $valuePlaceholder[] = ":$column$index";
                $parameters["$column$index"] = $value;
            }
            $valuesSql[] = '(' . implode(', ', $valuePlaceholder) . ')';
            $index++;
        }

        $sql .= implode(', ', $valuesSql);
        $this->sql = $sql . ';';
        $this->parameters = $parameters;
    }
}