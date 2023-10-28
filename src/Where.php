<?php declare(strict_types=1);

namespace Composite\DB;

class Where
{
    /**
     * @param string $string free format where string, example: "user_id = :user_id OR user_id > 0"
     * @param array<string, mixed> $params params with placeholders, which used in $string, example: ['user_id' => 123],
     */
    public function __construct(
        public readonly string $string,
        public readonly array $params,
    ) {
    }
}