<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema;

class SQLIndex
{
    public function __construct(
        public readonly ?string $name,
        public readonly bool $isUnique,
        public readonly array $columns,
        public readonly array $sort = [],
    ) {}
}