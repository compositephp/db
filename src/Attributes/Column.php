<?php declare(strict_types=1);

namespace Composite\DB\Attributes;

#[\Attribute]
class Column
{
    public function __construct(
        public readonly string|int|float|bool|null $default = null,
        public readonly ?int $size = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly ?bool $unsigned = null,
    ) {}
}
