<?php declare(strict_types=1);

namespace Composite\DB\Entity\Attributes;

#[\Attribute]
class PrimaryKey
{
    public function __construct(
        public readonly bool $autoIncrement = false,
    ) {}
}