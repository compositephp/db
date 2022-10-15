<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema;

class SQLEnum
{
    public function __construct(
        public readonly string $name,
        public readonly array $values = [],
    ) {}
}