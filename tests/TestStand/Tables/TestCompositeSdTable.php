<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestCompositeSdEntity;

class TestCompositeSdTable extends TestCompositeTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestCompositeSdEntity::schema());
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
        return $this->createEntities($this->findAllInternal(
            'user_id = :user_id',
            ['user_id' => $userId],
        ));
    }

    public function countAllByUser(int $userId): int
    {
        return $this->countAllInternal(
            'user_id = :user_id',
            ['user_id' => $userId],
        );
    }

    public function init(): bool
    {
        $this->getConnection()->executeStatement(
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