<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Tables;

use Composite\DB\Entity\Schema;
use Composite\DB\Tests\Table\TestStand\Entities\TestCompositeEntity;
use Composite\DB\Tests\Table\TestStand\Interfaces\ICompositeTable;

class TestCompositeTable extends \Composite\DB\AbstractTable implements ICompositeTable
{
    protected function getSchema(): Schema
    {
        return TestCompositeEntity::schema();
    }

    public function save(\Composite\DB\AbstractEntity|TestCompositeEntity &$entity): void
    {
        if ($entity->message === 'Exception') {
            throw new \Exception('Test Exception');
        }
        parent::save($entity);
    }

    public function findOne(int $user_id, int $post_id): ?TestCompositeEntity
    {
        return $this->createEntity($this->findOneInternal(['user_id' => $user_id, 'post_id' => $post_id]));
    }

    /**
     * @return TestCompositeEntity[]
     */
    public function findAllByUser(int $userId): array
    {
        return $this->createEntities($this->findAllInternal([
            'user_id' => $userId,
        ]));
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
                CONSTRAINT TestComposite PRIMARY KEY (`user_id`, `post_id`)
            );
            "
        );
        return true;
    }

    public function truncate(): void
    {
        $this->db->execute("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}