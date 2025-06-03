<?php declare(strict_types=1);

namespace Composite\DB\Helpers;

use Composite\DB\Exceptions\DbException;
use Doctrine\DBAL\Driver;

trait DatabaseSpecificTrait
{
    private ?bool $isPostgreSQL = null;
    private ?bool $isMySQL = null;
    private ?bool $isSQLite = null;

    private function identifyPlatform(): void
    {
        if ($this->isPostgreSQL !== null) {
            return;
        }
        $driver = $this->getConnection()->getDriver();
        if ($driver instanceof Driver\AbstractPostgreSQLDriver) {
            $this->isPostgreSQL = true;
            $this->isMySQL = $this->isSQLite = false;
        } elseif ($driver instanceof Driver\AbstractSQLiteDriver) {
            $this->isSQLite = true;
            $this->isPostgreSQL = $this->isMySQL = false;
        } elseif ($driver instanceof Driver\AbstractMySQLDriver) {
            $this->isMySQL = true;
            $this->isPostgreSQL = $this->isSQLite = false;
        } else {
            // @codeCoverageIgnoreStart
            throw new DbException('Unsupported driver ' . $driver::class);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareDataForSql(array $data): array
    {
        foreach ($data as $columnName => $value) {
            if (is_bool($value)) {
                $data[$columnName] = $value ? 1 : 0;
            }
        }
        return $data;
    }

    protected function escapeIdentifier(string $key): string
    {
        $this->identifyPlatform();
        if ($this->isMySQL) {
            if (strpos($key, '.')) {
                return implode('.', array_map(fn ($part) => "`$part`", explode('.', $key)));
            } else {
                return "`$key`";
            }
        } else {
            return '"' . $key . '"';
        }
    }
}
