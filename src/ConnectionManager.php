<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\Exceptions\DbException;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;

class ConnectionManager
{
    private const CONNECTIONS_CONFIG_ENV_VAR = 'CONNECTIONS_CONFIG_FILE';
    private static ?array $configs = null;
    private static array $connections = [];

    /**
     * @throws DbException
     */
    public static function getConnection(string $name, ?Configuration $config = null, ?EventManager $eventManager = null): Connection
    {
        if (!isset(self::$connections[$name])) {
            try {
                self::$connections[$name] = DriverManager::getConnection(
                    params: self::getConnectionParams($name),
                    config: $config,
                    eventManager: $eventManager,
                );
            } catch (Exception $e) {
                throw new DbException($e->getMessage(), $e->getCode(), $e);
            }
        }
        return self::$connections[$name];
    }

    /**
     * @throws DbException
     */
    private static function getConnectionParams(string $name): array
    {
        if (self::$configs === null) {
            $configFile = getenv(self::CONNECTIONS_CONFIG_ENV_VAR, true);
            if (empty($configFile)) {
                throw new DbException(sprintf(
                    'ConnectionManager is not configured, please call ConnectionManager::configure() method or setup putenv(\'%s=/path/to/config/file.php\') variable',
                    self::CONNECTIONS_CONFIG_ENV_VAR
                ));
            }
            if (!file_exists($configFile)) {
                throw new DbException(sprintf(
                    'Connections config file `%s` does not exist',
                    $configFile
                ));
            }
            $configContent = require_once $configFile;
            if (empty($configContent) || !is_array($configContent)) {
                throw new DbException(sprintf(
                    'Connections config file `%s` should return array of connection params',
                    $configFile
                ));
            }
            self::configure($configContent);
        }
        return self::$configs[$name] ?? throw new DbException("Connection config `$name` not found");
    }

    /**
     * @throws DbException
     */
    private static function configure(array $configs): void
    {
        foreach ($configs as $name => $connectionConfig) {
            if (empty($name) || !is_string($name)) {
                throw new DbException('Config has invalid connection name ' . var_export($name, true));
            }
            if (empty($connectionConfig) || !is_array($connectionConfig)) {
                throw new DbException("Connection `$name` has invalid connection params");
            }
            self::$configs[$name] = $connectionConfig;
        }
        self::$configs = $configs;
    }
}