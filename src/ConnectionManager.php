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
    /** @var array<string, array<string, mixed>>|null  */
    private static ?array $configs = null;
    /** @var array<string, Connection>  */
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
     * @return array<string, mixed>
     * @throws DbException
     */
    private static function getConnectionParams(string $name): array
    {
        if (self::$configs === null) {
            self::$configs = self::loadConfigs();
        }
        return self::$configs[$name] ?? throw new DbException("Connection config `$name` not found");
    }

    /**
     * @return array<string, array<string, mixed>>
     * @throws DbException
     */
    private static function loadConfigs(): array
    {
        $configFile = getenv(self::CONNECTIONS_CONFIG_ENV_VAR, true) ?: ($_ENV[self::CONNECTIONS_CONFIG_ENV_VAR] ?? false);
        if (empty($configFile)) {
            throw new DbException(sprintf(
                'ConnectionManager is not configured, please define ENV variable `%s`',
                self::CONNECTIONS_CONFIG_ENV_VAR
            ));
        }
        if (!file_exists($configFile)) {
            throw new DbException(sprintf(
                'Connections config file `%s` does not exist',
                $configFile
            ));
        }
        $configFileContent = require $configFile;
        if (empty($configFileContent) || !is_array($configFileContent)) {
            throw new DbException(sprintf(
                'Connections config file `%s` should return array of connection params',
                $configFile
            ));
        }
        $result = [];
        foreach ($configFileContent as $name => $connectionConfig) {
            if (empty($name) || !is_string($name)) {
                throw new DbException('Config has invalid connection name ' . var_export($name, true));
            }
            if (empty($connectionConfig) || !is_array($connectionConfig)) {
                throw new DbException("Connection `$name` has invalid connection params");
            }
            $result[$name] = $connectionConfig;
        }
        return $result;
    }
}