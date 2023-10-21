<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\MultiQuery\MultiInsert;
use Composite\DB\MultiQuery\MultiSelect;
use Composite\Entity\Helpers\DateTimeHelper;
use Composite\Entity\AbstractEntity;
use Composite\DB\Exceptions\DbException;
use Composite\Entity\Exceptions\EntityException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

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
            $this->checkUpdatedAt($entity);

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


            if ($this->config->hasOptimisticLock()
                && method_exists($entity, 'getVersion')
                && method_exists($entity, 'incrementVersion')) {
                $where['lock_version'] = $entity->getVersion();
                $entity->incrementVersion();
                $changedColumns['lock_version'] = $entity->getVersion();

                try {
                    $connection->beginTransaction();
                    $versionUpdated = $connection->update(
                        $this->getTableName(),
                        $changedColumns,
                        $where
                    );
                    if (!$versionUpdated) {
                        throw new Exceptions\LockException('Failed to update entity version, concurrency modification, rolling back.');
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
     * @throws \Throwable
     */
    public function saveMany(array $entities): void
    {
        $rowsToInsert = [];
        foreach ($entities as $i => $entity) {
            if ($entity->isNew()) {
                $this->config->checkEntity($entity);
                $this->checkUpdatedAt($entity);
                $rowsToInsert[] = $this->formatData($entity->toArray());
                unset($entities[$i]);
            }
        }
        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($entities as $entity) {
                $this->save($entity);
            }
            if ($rowsToInsert) {
                $chunks = array_chunk($rowsToInsert, 1000);
                foreach ($chunks as $chunk) {
                    $multiInsert = new MultiInsert(
                        tableName: $this->getTableName(),
                        rows: $chunk,
                    );
                    if ($multiInsert->getSql()) {
                        $stmt = $this->getConnection()->prepare($multiInsert->getSql());
                        $stmt->executeQuery($multiInsert->getParameters());
                    }
                }
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param AbstractEntity $entity
     * @throws \Throwable
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
     * @throws \Throwable
     */
    public function deleteMany(array $entities): void
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($entities as $entity) {
                $this->delete($entity);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
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
     * @param array<string, string>|string $orderBy
     * @return array<string, mixed>|null
     * @throws \Doctrine\DBAL\Exception
     */
    protected function findOneInternal(array $where, array|string $orderBy = []): ?array
    {
        $query = $this->select();
        $this->buildWhere($query, $where);
        $this->applyOrderBy($query, $orderBy);
        return $query->fetchAssociative() ?: null;
    }

    /**
     * @param array<int|string|array<string,mixed>> $pkList
     * @return array<array<string, mixed>>
     * @throws DbException
     * @throws \Doctrine\DBAL\Exception
     */
    protected function findMultiInternal(array $pkList): array
    {
        if (!$pkList) {
            return [];
        }
        $multiSelect = new MultiSelect($this->getConnection(), $this->config, $pkList);
        return $multiSelect->getQueryBuilder()->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param array<string, mixed> $whereParams
     * @param array<string, string>|string $orderBy
     * @return list<array<string,mixed>>
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
        $this->applyOrderBy($query, $orderBy);
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
            /** @var class-string<AbstractEntity> $entityClass */
            $entityClass = $this->config->entityClass;
            return $entityClass::fromArray($data);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return AbstractEntity[]
     */
    final protected function createEntities(mixed $data, ?string $keyColumnName = null): array
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
                if ($keyColumnName && isset($datum[$keyColumnName])) {
                    $result[$datum[$keyColumnName]] = $entityClass::fromArray($datum);
                } else {
                    $result[] = $entityClass::fromArray($datum);
                }
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
    protected function getPkCondition(int|string|array|AbstractEntity|UuidInterface $data): array
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
    private function buildWhere(\Doctrine\DBAL\Query\QueryBuilder $query, array $where): void
    {
        foreach ($where as $column => $value) {
            if ($value === null) {
                $query->andWhere("$column IS NULL");
            } elseif (is_array($value)) {
                $query
                    ->andWhere($query->expr()->in($column, $value));
            } else {
                $query
                    ->andWhere("$column = :" . $column)
                    ->setParameter($column, $value);
            }
        }
    }

    private function checkUpdatedAt(AbstractEntity $entity): void
    {
        if ($this->config->hasUpdatedAt() && property_exists($entity, 'updated_at') && $entity->updated_at === null) {
            $entity->updated_at = new \DateTimeImmutable();
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws \Doctrine\DBAL\Exception
     */
    private function formatData(array $data): array
    {
        $supportsBoolean = $this->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform;
        foreach ($data as $columnName => $value) {
            if (is_bool($value) && !$supportsBoolean) {
                $data[$columnName] = $value ? 1 : 0;
            }
        }
        return $data;
    }

    /**
     * @param array<string, string>|string $orderBy
     */
    private function applyOrderBy(QueryBuilder $query, string|array $orderBy): void
    {
        if (!$orderBy) {
            return;
        }
        if (is_array($orderBy)) {
            foreach ($orderBy as $column => $direction) {
                $query->addOrderBy($column, $direction);
            }
        } else {
            foreach (explode(',', $orderBy) as $orderByPart) {
                $orderByPart = trim($orderByPart);
                if (preg_match('/(.+)\s(asc|desc)$/i', $orderByPart, $orderByPartMatch)) {
                    $query->addOrderBy($orderByPartMatch[1], $orderByPartMatch[2]);
                } else {
                    $query->addOrderBy($orderByPart);
                }
            }
        }
    }
}
