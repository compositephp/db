<?php declare(strict_types=1);

namespace Composite\DB\Entity;

interface CastableInterface
{
    /**
     * @param mixed $dbValue value from your database
     * @return static|null value for your Entity, null if impossible to cast
     */
    public static function cast(mixed $dbValue): ?static;

    /**
     * @return string|int|null value for your database, null if impossible to uncast
     */
    public function uncast(): string|int|null;
}