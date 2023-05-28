<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities\Castable;

use Composite\Entity\Helpers\DateTimeHelper;
use Composite\Entity\CastableInterface;

class TestCastableIntObject extends \DateTime implements CastableInterface
{
    public function __construct(int $unixTime)
    {
        parent::__construct(date(DateTimeHelper::DATETIME_FORMAT, $unixTime));
    }

    public static function cast(mixed $dbValue): ?static
    {
        if (!$dbValue || !is_numeric($dbValue) || intval($dbValue) != $dbValue || $dbValue < 0) {
            return null;
        }
        try {
            return new static((int)$dbValue);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function uncast(): ?int
    {
        $unixTime = (int)$this->format('U');
        return $unixTime === 0 ? null : $unixTime ;
    }
}