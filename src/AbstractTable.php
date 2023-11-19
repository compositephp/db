<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\MultiQuery\MultiInsert;
use Composite\DB\MultiQuery\MultiSelect;
use Composite\Entity\Helpers\DateTimeHelper;
use Composite\Entity\AbstractEntity;
use Composite\DB\Exceptions\DbException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Ramsey\Uuid\UuidInterface;

abstract class AbstractTable
{
    use SelectRawTrait;

    protected readonly TableConfig $config;

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
            }
            $entityUpdated = $connection->update(
                table: $this->getTableName(),
                data: $changedColumns,
                criteria: $where,
            );
            if ($this->config->hasOptimisticLock() && !$entityUpdated) {
                throw new Exceptions\LockException('Failed to update entity version, concurrency modification, rolling back.');
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
     * @param array<string, mixed>|Where $where
     * @throws \Doctrine\DBAL\Exception
     */
    protected function _countAll(array|Where $where = []): int
    {
        $query = $this->select('COUNT(*)');
        if (is_array($where)) {
            $this->buildWhere($query, $where);
        } else {
            $query->where($where->condition);
            foreach ($where->params as $param => $value) {
                $query->setParameter($param, $value);
            }
        }
        return intval($query->executeQuery()->fetchOne());
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @return AbstractEntity|null
     */
    protected function _findByPk(mixed $pk): mixed
    {
        $where = $this->getPkCondition($pk);
        return $this->_findOne($where);
    }

    /**
     * @param array<string, mixed>|Where $where
     * @param array<string, string>|string $orderBy
     * @return AbstractEntity|null
     * @throws \Doctrine\DBAL\Exception
     */
    protected function _findOne(array|Where $where, array|string $orderBy = []): mixed
    {
        return $this->createEntity($this->_findOneRaw($where, $orderBy));
    }

    /**
     * @param array<int|string|array<string,mixed>> $pkList
     * @return array<AbstractEntity>| array<array-key, AbstractEntity>
     * @throws DbException
     * @throws \Doctrine\DBAL\Exception
     */
    protected function _findMulti(array $pkList, ?string $keyColumnName = null): array
    {
        if (!$pkList) {
            return [];
        }
        $multiSelect = new MultiSelect($this->getConnection(), $this->config, $pkList);
        return $this->createEntities(
            $multiSelect->getQueryBuilder()->executeQuery()->fetchAllAssociative(),
            $keyColumnName,
        );
    }

    /**
     * @param array<string, mixed>|Where $where
     * @param array<string, string>|string $orderBy
     * @return array<AbstractEntity>| array<array-key, AbstractEntity>
     */
    protected function _findAll(
        array|Where $where = [],
        array|string $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
        ?string $keyColumnName = null,
    ): array
    {
        return $this->createEntities(
            data: $this->_findAllRaw(
                where: $where,
                orderBy: $orderBy,
                limit: $limit,
                offset: $offset,
            ),
            keyColumnName: $keyColumnName,
        );
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
                if (is_array($datum)) {
                    if ($keyColumnName && isset($datum[$keyColumnName])) {
                        $result[$datum[$keyColumnName]] = $entityClass::fromArray($datum);
                    } else {
                        $result[] = $entityClass::fromArray($datum);
                    }
                } elseif ($datum instanceof $this->config->entityClass) {
                    if ($keyColumnName && property_exists($datum, $keyColumnName)) {
                        $result[$datum->{$keyColumnName}] = $datum;
                    } else {
                        $result[] = $datum;
                    }
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
}
