<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

use Composite\DB\Exceptions\EntityException;

class UnitEnumColumn extends AbstractColumn
{
    /**
     * @throws EntityException
     */
    public function cast(mixed $dbValue): \UnitEnum
    {
        /** @var \UnitEnum $enumClass */
        $enumClass = $this->type;
        if ($dbValue instanceof $enumClass) {
            return $dbValue;
        }
        foreach ($enumClass::cases() as $enum) {
            if ($enum->name === $dbValue) {
                return $enum;
            }
        }
        throw new EntityException("Case `$dbValue` not found in Enum `{$this->type}`");
    }

    /**
     * @param \UnitEnum $entityValue
     */
    public function uncast(mixed $entityValue): string
    {
        return $entityValue->name;
    }
}