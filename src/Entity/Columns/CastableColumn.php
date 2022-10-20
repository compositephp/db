<?php declare(strict_types=1);

namespace Composite\DB\Entity\Columns;

use Composite\DB\Entity\CastableInterface;

class CastableColumn extends AbstractColumn
{
    public function cast(mixed $dbValue): ?CastableInterface
    {
        /** @var CastableInterface $class */
        $class = $this->type;
        return $class::cast($dbValue);
    }

    /**
     * @param CastableInterface|mixed $entityValue
     */
    public function uncast(mixed $entityValue): int|string|null
    {
        return $entityValue->uncast();
    }
}