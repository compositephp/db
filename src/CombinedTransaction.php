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
    public function save(AbstractTable $table, AbstractEntity $entity): void
    {
        if (!$entity->isNew() && !$entity->getChangedColumns()) {
            return;
        }
        $this->doInTransaction($table, fn() => $table->save($entity));
    }

    /**
     * @param AbstractTable $table
     * @param AbstractEntity[] $entities
     * @throws DbException
     */
    public function saveMany(AbstractTable $table, array $entities): void
    {
        if (!$entities) {
            return;
        }
        $this->doInTransaction($table, fn () => $table->saveMany($entities));
    }

    /**
     * @param AbstractTable $table
     * @param AbstractEntity[] $entities
     * @throws DbException
     */
    public function deleteMany(AbstractTable $table, array $entities): void
    {
        if (!$entities) {
            return;
        }
        $this->doInTransaction($table, fn () => $table->deleteMany($entities));
    }

    /**
     * @throws Exceptions\DbException
     */
    public function delete(AbstractTable $table, AbstractEntity $entity): void
    {
        $this->doInTransaction($table, fn () => $table->delete($entity));
    }

    /**
     * @throws Exceptions\DbException
     */
    public function try(callable $callback): void
    {
        try {
            $callback();
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
                $connection->commit();
            // I have no idea how to simulate failed commit
            // @codeCoverageIgnoreStart
            } catch (\Throwable $e) {
                $this->rollback();
                throw new Exceptions\DbException($e->getMessage(), 500, $e);
            }
            // @codeCoverageIgnoreEnd
        }
        $this->finish();
    }

    /**
     * Pessimistic lock
     * @param string[] $keyParts
     * @throws DbException
     * @throws InvalidArgumentException
     */
    public function lock(CacheInterface $cache, array $keyParts, int $duration = 10): void
    {
        $this->cache = $cache;
        $this->lockKey = $this->buildLockKey($keyParts);
        if ($this->cache->get($this->lockKey)) {
            throw new DbException("Failed to get lock `{$this->lockKey}`");
        }
        if (!$this->cache->set($this->lockKey, 1, $duration)) {
            throw new DbException("Failed to save lock `{$this->lockKey}`");
        }
    }

    public function releaseLock(): void
    {
        if (!$this->cache || !$this->lockKey) {
            return;
        }
        if (!$this->cache->delete($this->lockKey)) {
            // @codeCoverageIgnoreStart
            throw new DbException("Failed to release lock `{$this->lockKey}`");
            // @codeCoverageIgnoreEnd
        }
    }

    private function doInTransaction(AbstractTable $table, callable $callback): void
    {
        try {
            $connectionName = $table->getConnectionName();
            if (empty($this->transactions[$connectionName])) {
                $connection = ConnectionManager::getConnection($connectionName);
                $connection->beginTransaction();
                $this->transactions[$connectionName] = $connection;
            }
            $callback();
        } catch (\Throwable $e) {
            $this->rollback();
            throw new Exceptions\DbException($e->getMessage(), 500, $e);
        }
    }

    /**
     * @param string[] $keyParts
     * @return string
     */
    private function buildLockKey(array $keyParts): string
    {
        $keyParts = array_merge(['composite', 'lock'], $keyParts);
        $result = implode('.', $keyParts);
        if (strlen($result) > 64) {
            $result = sha1($result);
        }
        return $result;
    }

    private function finish(): void
    {
        $this->transactions = [];
        $this->releaseLock();
    }
}