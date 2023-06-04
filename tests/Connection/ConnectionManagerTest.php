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

    public static function invalidConfig_dataProvider(): array
    {
        $testStandConfigsBaseDir = __DIR__ . '/../TestStand/configs/';
        return [
            [
                '',
            ],
            [
                'invalid/path',
            ],
            [
                $testStandConfigsBaseDir . 'empty_config.php',
            ],
            [
                $testStandConfigsBaseDir . 'wrong_content_config.php',
            ],
            [
                $testStandConfigsBaseDir . 'wrong_name_config.php',
            ],
            [
                $testStandConfigsBaseDir . 'wrong_params_config.php',
            ],
            [
                $testStandConfigsBaseDir . 'wrong_doctrine_config.php',
            ],
        ];
    }

    /**
     * @dataProvider invalidConfig_dataProvider
     */
    public function test_invalidConfig(string $configPath): void
    {
        $reflection = new \ReflectionClass(ConnectionManager::class);
        $reflection->setStaticPropertyValue('configs', null);
        $currentPath = getenv('CONNECTIONS_CONFIG_FILE');
        putenv('CONNECTIONS_CONFIG_FILE=' . $configPath);

        try {
            ConnectionManager::getConnection('db1');
            $this->fail('This line should not be reached');
        } catch (DbException) {
            $this->assertTrue(true);
        } finally {
            putenv('CONNECTIONS_CONFIG_FILE=' . $currentPath);
            $reflection->setStaticPropertyValue('configs', null);
        }
    }

    public function test_getConnectionWithMissingName(): void
    {
        $this->expectException(DbException::class);
        ConnectionManager::getConnection('invalid_name');
    }
}