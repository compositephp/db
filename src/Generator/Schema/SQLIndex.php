<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema;

use Composite\DB\Migrations\Config;

class SQLIndex
{
    public function __construct(
        public readonly ?string $name,
        public readonly bool $isUnique,
        public readonly array $columns,
        public readonly array $sort = [],
    ) {}
}