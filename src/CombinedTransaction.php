<?php declare(strict_types=1);

namespace Composite\DB;

class CombinedTransaction
{
    /** @var \Cycle\Database\DatabaseInterface[] */
    private array $transactions = [];

    /**
     * @throws Exceptions\DbException
     */
    public function save(AbstractTable $table, AbstractEntity &$entity): void
    {
        try {
            $db = $table->getDb();
            if (empty($this->transactions[$db->getName()])) {
                $db->begin();
                $this->transactions[$db->getName()] = $db;
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
            $db = $table->getDb();
            if (empty($this->transactions[$db->getName()])) {
                $db->begin();
                $this->transactions[$db->getName()] = $db;
            }
            $table->delete($entity);
        } catch (\Throwable $e) {
            $this->rollback();
            throw new Exceptions\DbException($e->getMessage(), 500, $e);
        }
    }

    public function rollback(): void
    {
        foreach ($this->transactions as $db) {
            $db->rollback();
        }
        $this->transactions = [];
    }

    /**
     * @throws Exceptions\DbException
     */
    public function commit(): void
    {
        foreach ($this->transactions as $db) {
            try {
                if (!$db->commit()) {
                    throw new Exceptions\DbException("Could not commit transaction for database `{$db->getName()}`");
                }
            } catch (\Throwable $e) {
                $this->rollback();
                throw new Exceptions\DbException($e->getMessage(), 500, $e);
            }
        }
        $this->transactions = [];
    }
}