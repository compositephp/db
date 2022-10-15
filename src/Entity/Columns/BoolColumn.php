<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

class BoolColumn extends AbstractColumn
{
    public function cast(mixed $dbValue): bool
    {
        if (is_string($dbValue) && strcasecmp($dbValue, 'false') === 0) {
            return false;
        }
        return boolval($dbValue);
    }

    public function uncast(mixed $entityValue): bool
    {
        return boolval($entityValue);
    }
}