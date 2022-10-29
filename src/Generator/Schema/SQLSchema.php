<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema;

use Composite\DB\Generator\Schema\Parsers\MySQLSchemaParser;
use Composite\DB\Generator\Schema\Parsers\PostgresSchemaParser;
use Composite\DB\Generator\Schema\Parsers\SQLiteSchemaParser;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Driver\MySQL\MySQLDriver;
use Cycle\Database\Driver\Postgres\PostgresDriver;
use Cycle\Database\Driver\SQLite\SQLiteDriver;

class SQLSchema
{

    /**
     * @param string $tableName
     * @param SQLColumn[] $columns
     * @param SQLEnum[] $enums
     * @param SQLIndex[] $indexes
     * @param string[] $primaryKeys
     */
    public function __construct(
        public readonly string $tableName,
        public readonly array $columns,
        public readonly array $enums,
        public readonly array $primaryKeys,
        public readonly array $indexes,
    ) {}

    public static function generate(DatabaseInterface $db, string $tableName): SQLSchema
    {
        $driverClass = $db->getDriver()::class;
        if ($driverClass === SQLiteDriver::class) {
            $tableSql = $db->query(SQLiteSchemaParser::TABLE_SQL, [':tableName' => $tableName])->fetch()['sql'];
            $indexesRows = $db->query(SQLiteSchemaParser::INDEXES_SQL, [':tableName' => $tableName])->fetchAll();
            $indexesSql = [];
            foreach ($indexesRows as $indexRow) {
                if (!empty($indexRow['sql'])) {
                    $indexesSql[] = $indexRow['sql'];
                }
            }
            $parser = new SQLiteSchemaParser($tableSql, $indexesSql);
            return $parser->getSchema();
        } elseif ($driverClass === MySQLDriver::class) {
            $sql = $db->query("SHOW CREATE TABLE $tableName")->fetch()['Create Table'];
            $parser = new MySQLSchemaParser($sql);
            return $parser->getSchema();
        } elseif ($driverClass === PostgresDriver::class) {
            $columns = $db->query(PostgresSchemaParser::COLUMNS_SQL, [':tableName' => $tableName])->fetchAll();
            $indexes = $db->query(PostgresSchemaParser::INDEXES_SQL, [':tableName' => $tableName])->fetchAll();
            if ($primaryKeySQL = PostgresSchemaParser::getPrimaryKeySQL($tableName)) {
                $primaryKeys = array_map(
                    fn(array $row): string => $row['column_name'],
                    $db->query($primaryKeySQL)->fetchAll()
                );
            } else {
                $primaryKeys = [];
            }
            $allEnumsRaw = $db->query(PostgresSchemaParser::ALL_ENUMS_SQL)->fetchAll();
            $allEnums = [];
            foreach ($allEnumsRaw as $enumRaw) {
                $name = $enumRaw['enum_name'];
                $value = $enumRaw['enum_value'];
                if (!isset($allEnums[$name])) {
                    $allEnums[$name] = [];
                }
                $allEnums[$name][] = $value;
            }
            $parser = new PostgresSchemaParser(
                tableName: $tableName,
                informationSchemaColumns: $columns,
                informationSchemaIndexes: $indexes,
                primaryKeys: $primaryKeys,
                allEnums: $allEnums,
            );
            return $parser->getSchema();
        } else {
            throw new \Exception("Driver `$driverClass` is not yet supported");
        }
    }

    public function isPrimaryKey(string $name): bool
    {
        return \in_array($name, $this->primaryKeys);
    }
}