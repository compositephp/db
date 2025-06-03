<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\Exceptions\DbException;
use Composite\DB\MultiQuery\MultiInsert;
use Composite\DB\MultiQuery\MultiSelect;
use Composite\Entity\AbstractEntity;
use Composite\Entity\Columns;
use Composite\Entity\Helpers\DateTimeHelper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Ramsey\Uuid\UuidInterface;

abstract class AbstractTable
{
    use Helpers\SelectRawTrait;
    use Helpers\DatabaseSpecificTrait;

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
    public function save(AbstractEntity $entity): void
    {
        $this->config->checkEntity($entity);
        if ($entity->isNew()) {
            $connection = $this->getConnection();
            $this->checkUpdatedAt($entity);

            $insertData = $entity->toArray();
            $preparedInsertData = $this->prepareDataForSql($insertData);
            $this->getConnection()->insert(
                table: $this->getTableName(),
                data: $preparedInsertData,
                types: $this->getDoctrineTypes($insertData),
            );

            if ($this->config->autoIncrementKey && ($lastInsertedId = $connection->lastInsertId())) {
                $insertData[$this->config->autoIncrementKey] = intval($lastInsertedId);
                $entity::schema()
                    ->getColumn($this->config->autoIncrementKey)
                    ->setValue($entity, $insertData[$this->config->autoIncrementKey]);
            }
            $entity->resetChangedColumns($insertData);
        } else {
            if (!$changedColumns = $entity->getChangedColumns()) {
                return;
            }
            if ($this->config->hasUpdatedAt() && property_exists($entity, 'updated_at')) {
                $entity->updated_at = new \DateTimeImmutable();
                $changedColumns['updated_at'] = DateTimeHelper::dateTimeToString($entity->updated_at);
            }
            $whereParams = $this->getPkCondition($entity);
            if ($this->config->hasOptimisticLock()
                && method_exists($entity, 'getVersion')
                && method_exists($entity, 'incrementVersion')) {
                $whereParams['lock_version'] = $entity->getVersion();
                $entity->incrementVersion();
                $changedColumns['lock_version'] = $entity->getVersion();
            }
            $updateString = implode(', ', array_map(fn ($key) => $this->escapeIdentifier($key) . "=?", array_keys($changedColumns)));
            $whereString = implode(' AND ', array_map(fn ($key) => $this->escapeIdentifier($key) . "=?", array_keys($whereParams)));
            $preparedParams = array_merge(
                array_values($this->prepareDataForSql($changedColumns)),
                array_values($this->prepareDataForSql($whereParams)),
            );
            $types = array_merge(
                $this->getDoctrineTypes($changedColumns),
                $this->getDoctrineTypes($whereParams),
            );

            $entityUpdated = (bool)$this->getConnection()->executeStatement(
                sql: "UPDATE " . $this->escapeIdentifier($this->getTableName()) . " SET $updateString WHERE $whereString;",
                params: $preparedParams,
                types: $types,
            );
            if ($this->config->hasOptimisticLock() && !$entityUpdated) {
                throw new Exceptions\LockException('Failed to update entity version, concurrency modification, rolling back.');
            }
            $entity->resetChangedColumns($changedColumns);
        }
    }

    private function getDoctrineTypes(array $data): array
    {
        $result = [];
        foreach ($data as $value) {
            if (is_bool($value)) {
                $result[] = ParameterType::BOOLEAN;
            } elseif (is_int($value)) {
                $result[] = ParameterType::INTEGER;
            } elseif (is_null($value)) {
                $result[] = ParameterType::NULL;
            } else {
                $result[] = ParameterType::STRING;
            }
        }
        return $result;
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
                $rowsToInsert[] = $this->prepareDataForSql($entity->toArray());
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
                $connection = $this->getConnection();
                foreach ($chunks as $chunk) {
                    $multiInsert = new MultiInsert(
                        connection: $connection,
                        tableName: $this->getTableName(),
                        rows: $chunk,
                    );
                    if ($multiInsert->getSql()) {
                        $connection->executeStatement(
                            sql: $multiInsert->getSql(),
                            params: $multiInsert->getParameters(),
                            types: $this->getDoctrineTypes(array_keys($chunk[0])),
                        );
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
    public function delete(AbstractEntity $entity): void
    {
        $this->config->checkEntity($entity);
        if ($this->config->hasSoftDelete()) {
            if (method_exists($entity, 'delete')) {
                $entity->delete();
                $this->save($entity);
            }
        } else {
            $whereParams = $this->getPkCondition($entity);
            $whereString = implode(' AND ', array_map(fn ($key) => $this->escapeIdentifier($key) . "=?", array_keys($whereParams)));
            $this->getConnection()->executeQuery(
                sql: "DELETE FROM " . $this->escapeIdentifier($this->getTableName()) . " WHERE $whereString;",
                params: array_values($whereParams),
            );
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
        $whereParams = $this->getPkCondition($pk);
        $whereString = implode(' AND ', array_map(fn ($key) => $this->escapeIdentifier($key) . "=?", array_keys($whereParams)));
        $row = $this->getConnection()
            ->executeQuery(
                sql: "SELECT * FROM " . $this->escapeIdentifier($this->getTableName()) . " WHERE $whereString;",
                params: array_values($whereParams),
            )
            ->fetchAssociative();
        return $this->createEntity($row);
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
        if (empty($this->config->primaryKeys)) {
            throw new \Exception("Primary keys are not defined in `" . $this::class . "` table config");
        }
        $condition = [];
        if ($data instanceof AbstractEntity) {
            if ($data->isNew()) {
                $data = $data->toArray();
            } else {
                foreach ($this->config->primaryKeys as $key) {
                    $condition[$key] = $data->getOldValue($key);
                }
                return $condition;
            }
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
}
