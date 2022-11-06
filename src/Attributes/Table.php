<?php declare(strict_types=1);

namespace Composite\DB\Attributes;

#[\Attribute]
class Table
{
    public function __construct(
        public readonly string $connection,
        public readonly string $name,
    ) {}
}
