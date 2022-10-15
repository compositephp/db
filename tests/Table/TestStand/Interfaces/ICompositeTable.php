<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Interfaces;

use Composite\DB\Tests\Table\TestStand\Entities\TestCompositeEntity;

interface ICompositeTable
{
    public function findOne(int $user_id, int $post_id): ?TestCompositeEntity;
    /**
     * @return TestCompositeEntity[]
     */
    public function findAllByUser(int $userId): array;
    public function countAllByUser(int $userId): int;
    public function truncate(): void;
}