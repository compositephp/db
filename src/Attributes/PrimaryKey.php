<?php declare(strict_types=1);

namespace Composite\DB\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class PrimaryKey
{
    public function __construct(
        public readonly bool $autoIncrement = false,
    ) {}
}