<?php declare(strict_types=1);

namespace Composite\DB;

use Composite\DB\Entity\Schema;
use Composite\DB\Exceptions\EntityException;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Query\SelectQuery;

abstract class AbstractTable
{
    protected DatabaseInterface $db;
    private ?SelectQuery $selectQuery = null;

    abstract protected function getSchema(): Schema;

    public function __construct(DatabaseProviderInterface $databaseProvider)
    {
        $this->db = $databaseProvider->database($this->getDatabaseName());
    }

    public function getDb(): DatabaseInterface
    {
        return $this->db;
    }

    /**
     * @param AbstractEntity $entity
     * @return void
     * @throws EntityException
     */
    public function save(AbstractEntity &$entity): void
    {
        $this->checkEntityIsLegal($entity);
        if ($entity->isNew()) {
            $insertData = $entity->toArray();
            $returnedValue = $this->db
                ->insert($this->getTableName())
                ->values($insertData)
                ->run();

            if ($returnedValue && ($autoIncrementColumn = $this->getSchema()->getAutoIncrementColumn())) {
                $insertData[$autoIncrementColumn->name] = $autoIncrementColumn->cast($returnedValue);
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
            $this->db->update(
                $this->getTableName(),
                $changedColumns,
                $where
            )->run();
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
        $this->checkEntityIsLegal($entity);
        if ($this->getSchema()->isSoftDelete) {
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
    public function transaction(callable $callback, ?string $isolationLevel = null)
    {
        return $this->db->transaction($callback, $isolationLevel);
    }

    protected function select(): SelectQuery
    {
        if ($this->selectQuery === null) {
            $this->selectQuery = $this->db->select()->from($this->getTableName());
        }
        return clone $this->selectQuery;
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
        return $this->getSchema()->getTableName() ?? throw new EntityException($this->getSchema()->class  . ' must have #[Table] attribute');
    }

    public function getDatabaseName(): string
    {
        return $this->getSchema()->getDatabaseName() ?? throw new EntityException($this->getSchema()->class  . ' must have #[Table] attribute');
    }

    final protected function createEntity(mixed $data): mixed
    {
        if (!$data) {
            return null;
        }
        try {
            /** @var AbstractEntity $class */
            $class = $this->getSchema()->class;
            return $class::fromArray($data);
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
            /** @var AbstractEntity $class */
            $class = $this->getSchema()->class;
            $result = [];
            foreach ($data as $datum) {
                if (!is_array($datum)) {
                    continue;
                }
                $result[] = $class::fromArray($datum);
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
            foreach ($this->getSchema()->getPrimaryKeyColumns() as $column) {
                $condition[$column->name] = $data[$column->name] ?? null;
            }
        } else {
            foreach ($this->getSchema()->getPrimaryKeyColumns() as $column) {
                $condition[$column->name] = $data;
            }
        }
        return $condition;
    }

    protected function enrichCondition(array &$condition): void
    {
        if ($this->getSchema()->isSoftDelete && !isset($condition['deleted_at'])) {
            $condition['deleted_at'] = null;
        }
    }

    private function checkEntityIsLegal(AbstractEntity $entity): void
    {
        if ($entity::class !== $this->getSchema()->class) {
            throw new EntityException(
                sprintf('Illegal entity `%s` passed to `%s`, only `%s` is allowed',
                    $entity::class,
                    $this::class,
                    $this->getSchema()->class,
                )
            );
        }
    }
}
