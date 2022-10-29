<?php declare(strict_types=1);

namespace Composite\DB\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS)]
class Index
{
    public function __construct(
        public readonly array $columns,
        public array $sort = [],
        public readonly bool $isUnique = false,
        public readonly ?string $name = null,
    ) {}

    /**
     * @psalm-return non-empty-string
     */
    public function generateName(string $tableName): string
    {
        $parts = [
            $tableName,
            $this->isUnique ? 'unq' : 'idx',
        ];
        foreach ($this->columns as $column) {
            $parts[] = $column;
            if (!empty($this->sort[$column])) {
                $parts[] = strtolower($this->sort[$column]);
            }
        }
        return implode('_', $parts);
    }
}