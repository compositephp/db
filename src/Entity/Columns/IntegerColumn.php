<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

class IntegerColumn extends AbstractColumn
{
    public function cast(mixed $dbValue): int
    {
        return intval($dbValue);
    }

    public function uncast(mixed $entityValue): int
    {
        return $entityValue;
    }
}