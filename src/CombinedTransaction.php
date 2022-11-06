<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\Exceptions\DbException;
use Composite\Entity\AbstractEntity;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class CombinedTransaction
{
    /** @var \Doctrine\DBAL\Connection[] */
    private array $transactions = [];
    private ?string $lockKey = null;
    private ?CacheInterface $cache = null;

    /**
     * @throws Exceptions\DbException
     */
    public function save(AbstractTable $table, AbstractEntity &$entity): void
    {
        try {
            $connectionName = $table->getConnectionName();
            if (empty($this->transactions[$connectionName])) {
                $connection = ConnectionManager::getConnection($connectionName);
                $connection->beginTransaction();
                $this->transactions[$connectionName] = $connection;
            }
            $table->save($entity);
        } catch (\Throwable $e) {
            $this->rollback();
            throw new Exceptions\DbException($e->getMessage(), 500, $e);
        }
    }

    /**
     * @throws Exceptions\DbException
     */
    public function delete(AbstractTable $table, AbstractEntity &$entity): void
    {
        try {
            $connectionName = $table->getConnectionName();
            if (empty($this->transactions[$connectionName])) {
                $connection = ConnectionManager::getConnection($connectionName);
                $connection->beginTransaction();
                $this->transactions[$connectionName] = $connection;
            }
            $table->delete($entity);
        } catch (\Throwable $e) {
            $this->rollback();
            throw new Exceptions\DbException($e->getMessage(), 500, $e);
        }
    }

    public function rollback(): void
    {
        foreach ($this->transactions as $connection) {
            $connection->rollBack();
        }
        $this->finish();
    }

    /**
     * @throws Exceptions\DbException
     */
    public function commit(): void
    {
        foreach ($this->transactions as $connectionName => $connection) {
            try {
                if (!$connection->commit()) {
                    throw new Exceptions\DbException("Could not commit transaction for database `$connectionName`");
                }
            } catch (\Throwable $e) {
                $this->rollback();
                throw new Exceptions\DbException($e->getMessage(), 500, $e);
            }
        }
        $this->finish();
    }

    /**
     * Pessimistic lock
     * @throws DbException
     */
    public function lock(CacheInterface $cache, array $keyParts, int $duration = 10): void
    {
        $this->cache = $cache;
        $this->lockKey = implode('.', array_merge(['composite', 'lock'], $keyParts));
        if (strlen($this->lockKey) > 64) {
            $this->lockKey = sha1($this->lockKey);
        }
        try {
            if ($this->cache->get($this->lockKey)) {
                throw new DbException("Failed to get lock `{$this->lockKey}`");
            }
            if (!$this->cache->set($this->lockKey, 1, $duration)) {
                throw new DbException("Failed to save lock `{$this->lockKey}`");
            }
        } catch (InvalidArgumentException) {
            throw new DbException("Lock key is invalid `{$this->lockKey}`");
        }
    }

    public function releaseLock(): void
    {
        if (!$this->cache || !$this->lockKey) {
            return;
        }
        try {
            $this->cache->delete($this->lockKey);
        } catch (InvalidArgumentException) {}
    }

    private function finish(): void
    {
        $this->transactions = [];
        $this->releaseLock();
    }
}