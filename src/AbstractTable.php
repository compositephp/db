<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\Helpers\DateTimeHelper;
use Composite\Entity\AbstractEntity;
use Composite\DB\Exceptions\DbException;
use Composite\Entity\Exceptions\EntityException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
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
            if ($this->config->hasUpdatedAt() && property_exists($entity, 'updated_at') && $entity->updated_at === null) {
                $entity->updated_at = new \DateTimeImmutable();
            }
            $insertData = $this->formatData($entity->toArray());
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

            if ($this->config->hasUpdatedAt() && property_exists($entity, 'updated_at')) {
                $entity->updated_at = new \DateTimeImmutable();
                $changedColumns['updated_at'] = DateTimeHelper::dateTimeToString($entity->updated_at);
            }

            if ($this->config->hasOptimisticLock() && isset($entity->version)) {
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
        if ($this->config->hasSoftDelete()) {
            if (method_exists($entity, 'delete')) {
                $entity->delete();
                $this->save($entity);
            }
        } else {
            $where = $this->getPkCondition($entity);
            $this->getConnection()->delete($this->getTableName(), $where);
        }
    }

    /**
     * @param AbstractEntity[] $entities
     */
    public function deleteMany(array $entities): bool
    {
        return (bool)$this->getConnection()->transactional(function() use ($entities) {
            foreach ($entities as $entity) {
                $this->delete($entity);
            }
            return true;
        });
    }

    /**
     * @param array<string, mixed> $whereParams
     * @throws \Doctrine\DBAL\Exception
     */
    protected function countAllInternal(string $whereString = '', array $whereParams = []): int
    {
        $query = $this->select('COUNT(*)');
        if ($whereString) {
            $query->where($whereString);
            foreach ($whereParams as $param => $value) {
                $query->setParameter($param, $value);
            }
        }
        return intval($query->executeQuery()->fetchOne());
    }

    /**
     * @return array<string, mixed>|null
     * @throws EntityException
     * @throws \Doctrine\DBAL\Exception
     */
    protected function findByPkInternal(mixed $pk): ?array
    {
        $where = $this->getPkCondition($pk);
        return $this->findOneInternal($where);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<string, mixed>|null
     * @throws \Doctrine\DBAL\Exception
     */
    protected function findOneInternal(array $where): ?array
    {
        $query = $this->select();
        $this->buildWhere($query, $where);
        return $query->fetchAssociative() ?: null;
    }

    /**
     * @param array<string, mixed> $whereParams
     * @param array<string, string>|string $orderBy
     * @return array<string, mixed>
     * @throws \Doctrine\DBAL\Exception
     */
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

    /**
     * @return AbstractEntity[]
     */
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

    /**
     * @param int|string|array<string, mixed>|AbstractEntity $data
     * @return array<string, mixed>
     * @throws EntityException
     */
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

    protected function select(string $select = '*'): QueryBuilder
    {
        if ($this->selectQuery === null) {
            $this->selectQuery = $this->getConnection()->createQueryBuilder()->from($this->getTableName());
        }
        return (clone $this->selectQuery)->select($select);
    }

    /**
     * @param array<string, mixed> $where
     */
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws \Doctrine\DBAL\Exception
     */
    private function formatData(array $data): array
    {
        foreach ($data as $columnName => $value) {
            if ($value === null && $this->config->isPrimaryKey($columnName)) {
                unset($data[$columnName]);
                continue;
            }
            if ($this->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                if (is_bool($value)) {
                    $data[$columnName] = $value ? 1 : 0;
                }
            }
        }
        return $data;
    }
}
