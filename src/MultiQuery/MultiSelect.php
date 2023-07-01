<?php declare(strict_types=1);

namespace Composite\DB\MultiQuery;

use Composite\DB\Exceptions\DbException;
use Composite\DB\TableConfig;
use Composite\Entity\AbstractEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class MultiSelect
{
    private readonly QueryBuilder $queryBuilder;

    public function __construct(
        Connection $connection,
        TableConfig $tableConfig,
        array $condition,
    ) {
        $query = $connection->createQueryBuilder()->select('*')->from($tableConfig->tableName);
        /** @var class-string<AbstractEntity> $class */
        $class = $tableConfig->entityClass;

        $pkColumns = [];
        foreach ($tableConfig->primaryKeys as $primaryKeyName) {
            $pkColumns[$primaryKeyName] = $class::schema()->getColumn($primaryKeyName);
        }

        if (count($pkColumns) === 1) {
            if (!array_is_list($condition)) {
                throw new DbException('Input argument $pkList must be list');
            }
            /** @var \Composite\Entity\Columns\AbstractColumn $pkColumn */
            $pkColumn = reset($pkColumns);
            $preparedPkValues = array_map(fn ($pk) => $pkColumn->uncast($pk), $condition);
            $query->andWhere($query->expr()->in($pkColumn->name, $preparedPkValues));
        } else {
            $expressions = [];
            foreach ($condition as $i => $pkArray) {
                if (!is_array($pkArray)) {
                    throw new DbException('For tables with composite keys, input array must consist associative arrays');
                }
                $pkOrExpr = [];
                foreach ($pkArray as $pkName => $pkValue) {
                    if (is_string($pkName) && isset($pkColumns[$pkName])) {
                        $preparedPkValue = $pkColumns[$pkName]->cast($pkValue);
                        $pkOrExpr[] = $query->expr()->eq($pkName, ':' . $pkName . $i);
                        $query->setParameter($pkName . $i, $preparedPkValue);
                    }
                }
                if ($pkOrExpr) {
                    $expressions[] = $query->expr()->and(...$pkOrExpr);
                }
            }
            $query->where($query->expr()->or(...$expressions));
        }
        $this->queryBuilder = $query;
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }
}