<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\Entity\AbstractEntity;
use Composite\DB\Exceptions\DbException;
use Composite\Entity\Exceptions\EntityException;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Query\SelectQuery;

abstract class AbstractTable
{
    protected readonly TableConfig $config;
    protected DatabaseInterface $db;
    private ?SelectQuery $selectQuery = null;

    abstract protected function getConfig(): TableConfig;

    public function __construct(DatabaseProviderInterface $databaseProvider)
    {
        $this->config = $this->getConfig();
        $this->db = $databaseProvider->database($this->config->dbName);
    }

    public function getDb(): DatabaseInterface
    {
        return $this->db;
    }

    /**
     * @param AbstractEntity $entity
     * @return void
     * @throws \Throwable
     */
    public function save(AbstractEntity &$entity): void
    {
        $this->config->checkEntity($entity);
        if ($entity->isNew()) {
            $insertData = $entity->toArray();
            $returnedValue = $this->db
                ->insert($this->getTableName())
                ->values($insertData)
                ->run();

            if ($returnedValue && ($autoIncrementKey = $this->config->autoIncrementKey)) {
                $insertData[$autoIncrementKey] = intval($returnedValue);
                $entity = $entity::fromArray($insertData);
            } else {
                $entity->resetChangedColumns();
            }
        } else {
            if (!$changedColumns = $entity->getChangedColumns()) {
                return;
            }
            $where = $this->getPkCondition($entity);
            $this->enrichCondition($where);

            if ($this->config->isOptimisticLock && isset($entity->version)) {
                $currentVersion = $entity->version;
                $this->transaction(function () use ($changedColumns, $where, $entity, $currentVersion) {
                    $this->db->update(
                        $this->getTableName(),
                        $changedColumns,
                        $where
                    )->run();
                    $versionUpdated = $this->db->update(
                        $this->getTableName(),
                        ['version' => $currentVersion + 1],
                        $where + ['version' => $currentVersion]
                    )->run();
                    if (!$versionUpdated) {
                        throw new DbException('Failed to update entity version, concurrency modification, rolling back.');
                    }
                });
            } else {
                $this->db->update(
                    $this->getTableName(),
                    $changedColumns,
                    $where
                )->run();
            }
            $entity->resetChangedColumns();
        }
    }

    /**
     * @param AbstractEntity[] $entities
     * @return AbstractEntity[] $entities
     * @throws \Throwable
     */
    public function saveMany(array $entities): array
    {
        return $this->transaction(function() use ($entities) {
            foreach ($entities as $entity) {
                $this->save($entity);
            }
            return $entities;
        });
    }

    /**
     * @throws EntityException
     */
    public function delete(AbstractEntity &$entity): void
    {
        $this->config->checkEntity($entity);
        if ($this->config->isSoftDelete) {
            if (method_exists($entity, 'delete')) {
                $entity->delete();
                $this->save($entity);
            }
        } else {
            $this->db->delete($this->getTableName(), $this->getPkCondition($entity))->run();
        }
    }

    /**
     * @param AbstractEntity[] $entities
     */
    public function deleteMany(array $entities): bool
    {
        return $this->transaction(function() use ($entities) {
            foreach ($entities as $entity) {
                $this->delete($entity);
            }
            return true;
        });
    }

    protected function countAllInternal(array $where = []): int
    {
        $this->enrichCondition($where);
        return $this->select()->where($where)->count();
    }

    /**
     * @throws \Throwable
     */
    public function transaction(callable $callback, ?string $isolationLevel = null): mixed
    {
        return $this->db->transaction($callback, $isolationLevel);
    }

    protected function findByPkInternal(mixed $pk): ?array
    {
        $where = $this->getPkCondition($pk);
        return $this->findOneInternal($where);
    }

    protected function findOneInternal(array $where): ?array
    {
        $this->enrichCondition($where);
        $query = $this->select()->where($where);
        return $query->run()->fetch() ?: null;
    }

    protected function findAllInternal(array $where = [], array|string $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $this->enrichCondition($where);
        return $this
            ->select()
            ->where($where)
            ->orderBy($orderBy)
            ->limit($limit)
            ->offset($offset)
            ->fetchAll();
    }

    public function getTableName(): string
    {
        return $this->config->tableName;
    }

    public function getDatabaseName(): string
    {
        return $this->config->dbName;
    }

    final protected function createEntity(mixed $data): mixed
    {
        if (!$data) {
            return null;
        }
        try {
            /** @psalm-var class-string<AbstractEntity> $entityClass */
            $entityClass = $this->config->entityClass;
            return $entityClass::fromArray($data);
        } catch (\Throwable) {
            return null;
        }
    }

    final protected function createEntities(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }
        try {
            /** @psalm-var class-string<AbstractEntity> $entityClass */
            $entityClass = $this->config->entityClass;
            $result = [];
            foreach ($data as $datum) {
                if (!is_array($datum)) {
                    continue;
                }
                $result[] = $entityClass::fromArray($datum);
            }
        } catch (\Throwable) {
            return [];
        }
        return $result;
    }

    protected function getPkCondition(int|string|array|AbstractEntity $data): array
    {
        $condition = [];
        if ($data instanceof AbstractEntity) {
            $data = $data->toArray();
        }
        if (is_array($data)) {
            foreach ($this->config->primaryKeys as $key) {
                $condition[$key] = $data[$key] ?? null;
            }
        } else {
            foreach ($this->config->primaryKeys as $key) {
                $condition[$key] = $data;
            }
        }
        return $condition;
    }

    protected function enrichCondition(array &$condition): void
    {
        if ($this->config->isSoftDelete && !isset($condition['deleted_at'])) {
            $condition['deleted_at'] = null;
        }
    }

    protected function select(): SelectQuery
    {
        if ($this->selectQuery === null) {
            $this->selectQuery = $this->db->select()->from($this->getTableName());
        }
        return clone $this->selectQuery;
    }
}
