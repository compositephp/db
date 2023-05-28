<?php declare(strict_types=1);

namespace Composite\DB\Tests\Connection;

use Composite\DB\ConnectionManager;
use Composite\DB\Exceptions\DbException;
use Doctrine\DBAL\Connection;

final class ConnectionManagerTest extends \PHPUnit\Framework\TestCase
{
    public function test_getConnection(): void
    {
        $connection = ConnectionManager::getConnection('sqlite');
        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function test_getConnectionWithInvalidConfig(): void
    {
        putenv('CONNECTIONS_CONFIG_FILE=invalid/path');
        $this->expectException(DbException::class);

        ConnectionManager::getConnection('db1');
    }

    public function test_getConnectionWithMissingName(): void
    {
        $this->expectException(DbException::class);

        ConnectionManager::getConnection('missing_db');
    }
}