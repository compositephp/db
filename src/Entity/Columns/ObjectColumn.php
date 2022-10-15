<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

use Composite\DB\Exceptions\EntityException;

class ObjectColumn extends AbstractColumn
{
    /**
     * @throws EntityException
     */
    public function cast(mixed $dbValue): \stdClass
    {
        if ($dbValue instanceof \stdClass) {
            return $dbValue;
        }
        try {
            $decoded = \json_decode($dbValue, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new EntityException($e->getMessage(), $e);
        }
        if (!$decoded instanceof \stdClass) {
            throw new EntityException('Decoded result is not an object');
        }
        return $decoded;
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