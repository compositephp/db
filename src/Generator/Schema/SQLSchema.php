<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema;

use Composite\DB\Generator\Schema\Parsers\MySQLSchemaParser;
use Composite\DB\Generator\Schema\Parsers\PostgresSchemaParser;
use Composite\DB\Generator\Schema\Parsers\SQLiteSchemaParser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;

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

    /**
     * @throws \Exception
     */
    public static function generate(Connection $connection, string $tableName): SQLSchema
    {
        $driver = $connection->getDriver();
        if ($driver instanceof Driver\AbstractSQLiteDriver) {
            $parser = new SQLiteSchemaParser($connection, $tableName);
            return $parser->getSchema();
        } elseif ($driver instanceof Driver\AbstractMySQLDriver) {
            $parser = new MySQLSchemaParser($connection, $tableName);
            return $parser->getSchema();
        } elseif ($driver instanceof Driver\AbstractPostgreSQLDriver) {
            $parser = new PostgresSchemaParser($connection, $tableName);
            return $parser->getSchema();
        } else {
            throw new \Exception("Driver `" . $driver::class . "` is not yet supported");
        }
    }

    public function isPrimaryKey(string $name): bool
    {
        return \in_array($name, $this->primaryKeys);
    }
}