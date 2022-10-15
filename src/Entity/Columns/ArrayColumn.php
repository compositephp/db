<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

use Composite\DB\Exceptions\EntityException;

class ArrayColumn extends AbstractColumn
{
    /**
     * @throws EntityException
     */
    public function cast(mixed $dbValue): array
    {
        if (is_array($dbValue)) {
            return $dbValue;
        }
        try {
            return \json_decode($dbValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new EntityException($e->getMessage(), $e);
        }
    }

    /**
     * @throws EntityException
     */
    public function uncast(mixed $entityValue): string
    {
        try {
            return \json_encode($entityValue, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new EntityException($e->getMessage(), $e);
        }
    }
}