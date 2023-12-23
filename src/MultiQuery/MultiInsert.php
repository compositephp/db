<?php declare(strict_types=1);

namespace Composite\DB\MultiQuery;

use Composite\DB\Helpers\DatabaseSpecificTrait;
use Doctrine\DBAL\Connection;

class MultiInsert
{
    use DatabaseSpecificTrait;

    private Connection $connection;
    private string $sql = '';
    /** @var array<string, mixed> */
    private array $parameters = [];

    /**
     * @param string $tableName
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(Connection $connection, string $tableName, array $rows) {
        if (!$rows) {
            return;
        }
        $this->connection = $connection;
        $firstRow = reset($rows);
        $columnNames = array_map(fn ($columnName) => $this->escapeIdentifier($columnName), array_keys($firstRow));
        $this->sql = "INSERT INTO " . $this->escapeIdentifier($tableName)  . " (" . implode(', ', $columnNames) . ") VALUES ";
        $valuesSql = [];

        $index = 0;
        foreach ($rows as $row) {
            $valuePlaceholder = [];
            foreach ($row as $column => $value) {
                $valuePlaceholder[] = ":$column$index";
                $this->parameters["$column$index"] = $value;
            }
            $valuesSql[] = '(' . implode(', ', $valuePlaceholder) . ')';
            $index++;
        }

        $this->sql .= implode(', ', $valuesSql) . ';';
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    private function getConnection(): Connection
    {
        return $this->connection;
    }
}