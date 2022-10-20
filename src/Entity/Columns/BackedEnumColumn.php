<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

class BackedEnumColumn extends AbstractColumn
{
    public function cast(mixed $dbValue): \BackedEnum
    {
        /** @var \BackedEnum $enumClass */
        $enumClass = $this->type;
        if ($dbValue instanceof $enumClass) {
            return $dbValue;
        }
        if (is_numeric($dbValue)) {
            $dbValue = intval($dbValue);
        }
        return $enumClass::from($dbValue);
    }

    /**
     * @param \BackedEnum|mixed $entityValue
     */
    public function uncast(mixed $entityValue): int|string
    {
        return $entityValue->value;
    }
}