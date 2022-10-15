<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

use Composite\DB\AbstractEntity;
use Composite\DB\Exceptions\EntityException;

class EntityColumn extends AbstractColumn
{
    /**
     * @throws EntityException
     */
    public function cast(mixed $dbValue): AbstractEntity
    {
        /** @var AbstractEntity $className */
        $className = $this->type;
        if ($dbValue instanceof $className) {
            return $dbValue;
        }
        try {
            $data = \json_decode($dbValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new EntityException($e->getMessage(), $e);
        }
        return $className::fromArray($data);
    }

    /**
     * @param AbstractEntity $entityValue
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