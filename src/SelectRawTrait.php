<?php declare(strict_types=1);

namespace Composite\DB;

use Doctrine\DBAL\Query\QueryBuilder;

trait SelectRawTrait
{
    /** @var string[]  */
    private array $comparisonSigns = ['=', '!=', '>', '<', '>=', '<=', '<>'];

    private ?QueryBuilder $selectQuery = null;

    protected function select(string $select = '*'): QueryBuilder
    {
        if ($this->selectQuery === null) {
            $this->selectQuery = $this->getConnection()->createQueryBuilder()->from($this->getTableName());
        }
        return (clone $this->selectQuery)->select($select);
    }

    /**
     * @param array<string, mixed>|Where $where
     * @param array<string, string>|string $orderBy
     * @return array<string, mixed>|null
     * @throws \Doctrine\DBAL\Exception
     */
    private function _findOneRaw(array|Where $where, array|string $orderBy = []): ?array
    {
        $query = $this->select();
        $this->buildWhere($query, $where);
        $this->applyOrderBy($query, $orderBy);
        return $query->fetchAssociative() ?: null;
    }

    /**
     * @param array<string, mixed>|Where $where
     * @param array<string, string>|string $orderBy
     * @return list<array<string,mixed>>
     * @throws \Doctrine\DBAL\Exception
     */
    private function _findAllRaw(
        array|Where $where = [],
        array|string $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array
    {
        $query = $this->select();
        $this->buildWhere($query, $where);
        $this->applyOrderBy($query, $orderBy);
        if ($limit > 0) {
            $query->setMaxResults($limit);
        }
        if ($offset > 0) {
            $query->setFirstResult($offset);
        }
        return $query->executeQuery()->fetchAllAssociative();
    }


    /**
     * @param array<string, mixed>|Where $where
     */
    private function buildWhere(QueryBuilder $query, array|Where $where): void
    {
        if (is_array($where)) {
            foreach ($where as $column => $value) {
                if ($value instanceof \BackedEnum) {
                    $value = $value->value;
                } elseif ($value instanceof \UnitEnum) {
                    $value = $value->name;
                }

                if (is_null($value)) {
                    $query->andWhere($column . ' IS NULL');
                } elseif (is_array($value) && count($value) === 2 && \in_array($value[0], $this->comparisonSigns)) {
                    $comparisonSign = $value[0];
                    $comparisonValue = $value[1];

                    // Handle special case of "!= null"
                    if ($comparisonSign === '!=' && is_null($comparisonValue)) {
                        $query->andWhere($column . ' IS NOT NULL');
                    } else {
                        $query->andWhere($column . ' ' . $comparisonSign . ' :' . $column)
                            ->setParameter($column, $comparisonValue);
                    }
                } elseif (is_array($value)) {
                    $placeholders = [];
                    foreach ($value as $index => $val) {
                        $placeholders[] = ':' . $column . $index;
                        $query->setParameter($column . $index, $val);
                    }
                    $query->andWhere($column . ' IN(' . implode(', ', $placeholders) . ')');
                } else {
                    $query->andWhere($column . ' = :' . $column)
                        ->setParameter($column, $value);
                }
            }
        } else {
            $query->where($where->condition);
            foreach ($where->params as $param => $value) {
                $query->setParameter($param, $value);
            }
        }
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