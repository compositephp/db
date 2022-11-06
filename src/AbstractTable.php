<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\Entity\AbstractEntity;
use Composite\DB\Exceptions\DbException;
use Composite\Entity\Exceptions\EntityException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

abstract class AbstractTable
{
    protected readonly TableConfig $config;
    private ?QueryBuilder $selectQuery = null;

    abstract protected function getConfig(): TableConfig;

    public function __construct()
    {
        $this->config = $this->getConfig();
    }

    public function getTableName(): string
    {
        return $this->config->tableName;
    }

    protected function getConnection(): Connection
    {
        return ConnectionManager::getConnection($this->config->connectionName);
    }

    public function getConnectionName(): string
    {
        return $this->config->connectionName;
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
            $connection = $this->getConnection();
            $insertData = $entity->toArray();
            $this->getConnection()->insert($this->getTableName(), $insertData);

            if ($this->config->autoIncrementKey) {
                $insertData[$this->config->autoIncrementKey] = intval($connection->lastInsertId());
                $entity = $entity::fromArray($insertData);
            } else {
                $entity->resetChangedColumns();
            }
        } else {
            if (!$changedColumns = $entity->getChangedColumns()) {
                return;
            }
            $connection = $this->getConnection();
            $where = $this->getPkCondition($entity);
            $this->enrichCondition($where);

            if ($this->config->isOptimisticLock && isset($entity->version)) {
                $currentVersion = $entity->version;
                try {
                    $connection->beginTransaction();
                    $connection->update(
                        $this->getTableName(),
                        $changedColumns,
                        $where
                    );
                    $versionUpdated = $connection->update(
                        $this->getTableName(),
                        ['version' => $currentVersion + 1],
                        $where + ['version' => $currentVersion]
                    );
                    if (!$versionUpdated) {
                        throw new DbException('Failed to update entity version, concurrency modification, rolling back.');
                    }
                    $connection->commit();
                } catch (\Throwable $e) {
                    $connection->rollBack();
                    throw $e;
                }
            } else {
                $connection->update(
                    $this->getTableName(),
                    $changedColumns,
                    $where
                );
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
        return $this->getConnection()->transactional(function() use ($entities) {
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
            $where = $this->getPkCondition($entity);
            $this->enrichCondition($where);
            $this->getConnection()->delete($this->getTableName(), $where);
        }
    }

    /**
     * @param AbstractEntity[] $entities
     */
    public function deleteMany(array $entities): bool
    {
        return $this->getConnection()->transactional(function() use ($entities) {
            foreach ($entities as $entity) {
                $this->delete($entity);
            }
            return true;
        });
    }

    protected function countAllInternal(string $whereString = '', array $whereParams = []): int
    {
        $query = $this->select('COUNT(*)');
        if ($whereString) {
            $query->where($whereString);
            foreach ($whereParams as $param => $value) {
                $query->setParameter($param, $value);
            }
        }
        $this->enrichCondition($query);
        return intval($query->executeQuery()->fetchOne());
    }

    protected function findByPkInternal(mixed $pk): ?array
    {
        $where = $this->getPkCondition($pk);
        return $this->findOneInternal($where);
    }

    protected function findOneInternal(array $where): ?array
    {
        $query = $this->select();
        $this->enrichCondition($where);
        $this->buildWhere($query, $where);
        return $query->fetchAssociative() ?: null;
    }

    protected function findAllInternal(
        string $whereString = '',
        array $whereParams = [],
        array|string $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array
    {
        $query = $this->select();
        if ($whereString) {
            $query->where($whereString);
            foreach ($whereParams as $param => $value) {
                $query->setParameter($param, $value);
            }
        }
        $this->enrichCondition($query);

        if ($orderBy) {
            if (is_array($orderBy)) {
                foreach ($orderBy as $column => $direction) {
                    $query->addOrderBy($column, $direction);
                }
            } else {
                $query->orderBy($orderBy);
            }
        }
        if ($limit > 0) {
            $query->setMaxResults($limit);
        }
        if ($offset > 0) {
            $query->setFirstResult($offset);
        }
        return $query->executeQuery()->fetchAllAssociative();
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

    protected function enrichCondition(array|QueryBuilder &$query): void
    {
        if ($this->config->isSoftDelete) {
            if ($query instanceof QueryBuilder) {
                $query->andWhere('deleted_at IS NULL');
            } else {
                if (!isset($query['deleted_at'])) {
                    $query['deleted_at'] = null;
                }
            }
        }
    }

    protected function select(string $select = '*'): QueryBuilder
    {
        if ($this->selectQuery === null) {
            $this->selectQuery = $this->getConnection()->createQueryBuilder()->from($this->getTableName());
        }
        return (clone $this->selectQuery)->select($select);
    }

    private function buildWhere(QueryBuilder $query, array $where): void
    {
        foreach ($where as $column => $value) {
            if ($value === null) {
                $query->andWhere("$column IS NULL");
            } else {
                $query->andWhere("$column = :" . $column);
                $query->setParameter($column, $value);
            }
        }
    }
}
