<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Tables;

use Composite\DB\Entity\Schema;
use Composite\DB\Tests\Table\TestStand\Entities\TestCompositeSdEntity;

class TestCompositeSdTable extends TestCompositeTable
{
    protected function getSchema(): Schema
    {
        return TestCompositeSdEntity::schema();
    }

    public function findOne(int $user_id, int $post_id): ?TestCompositeSdEntity
    {
        return $this->createEntity($this->findOneInternal([
            'user_id' => $user_id,
            'post_id' => $post_id,
        ]));
    }

    /**
     * @return TestCompositeSdEntity[]
     */
    public function findAllByUser(int $userId): array
    {
        return array_map(
            fn (array $data) => TestCompositeSdEntity::fromArray($data),
            $this->findAllInternal(['user_id' => $userId])
        );
    }

    public function countAllByUser(int $userId): int
    {
        return $this->countAllInternal(['user_id' => $userId]);
    }

    public function init(): bool
    {
        $this->db->execute(
            "
            CREATE TABLE IF NOT EXISTS {$this->getTableName()}
            (
                `user_id` integer not null,
                `post_id` integer not null,
                `message` VARCHAR(255) DEFAULT '' NOT NULL,
                `created_at` TIMESTAMP NOT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT TestCompositeSd PRIMARY KEY (`user_id`, `post_id`, `deleted_at`)
            );
            "
        );
        return true;
    }
}