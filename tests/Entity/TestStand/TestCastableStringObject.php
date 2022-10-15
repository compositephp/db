<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\TestStand;

use Composite\DB\Entity\CastableInterface;

class TestCastableStringObject implements CastableInterface
{
    public function __construct(private readonly ?string $value) {}

    public static function cast(mixed $dbValue): ?static
    {
        if (is_string($dbValue) || is_numeric($dbValue)) {
            $dbValue = trim((string)$dbValue);
            $dbValue = preg_replace('/(^_)|(_$)/', '', $dbValue);
        } else {
            $dbValue = null;
        }
        return new static($dbValue ?: null);
    }

    public function uncast(): ?string
    {
        return $this->value ? '_' . $this->value . '_' : null;
    }
}