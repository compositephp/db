<?php declare(strict_types=1);

namespace Composite\DB\Entity\Attributes;

#[\Attribute]
class Table
{
    public function __construct(
        public readonly string $db,
        public readonly ?string $name = null,
    ) {}
}
