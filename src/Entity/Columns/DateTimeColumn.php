<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

use Composite\DB\DateTimeHelper;

class DateTimeColumn extends AbstractColumn
{
    /**
     * @throws \Exception
     */
    public function cast(mixed $dbValue): ?\DateTimeInterface
    {
        if (DateTimeHelper::isDefault($dbValue)) {
            return null;
        }
        $class = $this->type;
        if (is_string($dbValue)) {
            return new $class($dbValue);
        } elseif ($dbValue instanceof $class) {
            return $dbValue;
        } else {
            return null;
        }
    }

    public function uncast(mixed $entityValue): ?string
    {
        if ($this->isNullable && DateTimeHelper::isDefault($entityValue)) {
            return null;
        }
        return DateTimeHelper::dateTimeToString($entityValue);
    }
}