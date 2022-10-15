<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Cycle\Database\Config;
use Cycle\Database\DatabaseManager;
use Kodus\Cache\FileCache;
use Psr\SimpleCache\CacheInterface;

abstract class BaseTableTest extends \PHPUnit\Framework\TestCase
{
    private static ?DatabaseManager $dbm = null;
    private static ?CacheInterface $cache = null;

    public static function getDatabaseManager(): DatabaseManager
    {
        if (self::$dbm === null) {
            self::$dbm = new DatabaseManager(new Config\DatabaseConfig([
                'databases' => [
                    'sqlite' => ['connection' => 'sqlite'],
                    'mysql' => ['connection' => 'mysql'],
                    'postgres' => ['connection' => 'postgres'],
                ],
                'connections' => [
                    'sqlite' => new Config\SQLiteDriverConfig(
                        connection: new Config\SQLite\FileConnectionConfig(
                            database: dirname(__DIR__) . '/runtime/sqlite/database.db'
                        ),
                    ),
                    'mysql' => new Config\MySQLDriverConfig(
                        connection: new Config\MySQL\TcpConnectionConfig(
                            database: 'test',
                            host: '127.0.0.1',
                            port: 3306,
                            user: 'test',
                            password: 'test',
                        ),
                    ),
                    'postgres' => new Config\PostgresDriverConfig(
                        connection: new Config\Postgres\TcpConnectionConfig(
                            database: 'test',
                            host: '127.0.0.1',
                            port: 5432,
                            user: 'test',
                            password: 'test',
                        ),
                    ),
                ],
            ]));
        }
        return self::$dbm;
    }

    public static function getCache(): CacheInterface
    {
        if (self::$cache === null) {
            self::$cache = new FileCache(dirname(__DIR__) . '/runtime/cache', 3600);
        }
        return self::$cache;
    }

    protected function getUniqueName(): string
    {
        return (new \DateTime())->format('Uu') . '_' . uniqid();
    }
}