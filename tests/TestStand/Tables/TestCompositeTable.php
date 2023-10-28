<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\Enums\TestUnitEnum;
use Composite\DB\Tests\TestStand\Entities\TestCompositeEntity;
use Composite\DB\Tests\TestStand\Interfaces\ICompositeTable;
use Composite\Entity\AbstractEntity;

class TestCompositeTable extends \Composite\DB\AbstractTable implements ICompositeTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestCompositeEntity::schema());
    }

    public function save(AbstractEntity|TestCompositeEntity &$entity): void
    {
        if ($entity->message === 'Exception') {
            throw new \Exception('Test Exception');
        }
        parent::save($entity);
    }

    public function delete(AbstractEntity|TestCompositeEntity &$entity): void
    {
        if ($entity->message === 'Exception') {
            throw new \Exception('Test Exception');
        }
        parent::delete($entity);
    }

    public function findOne(int $user_id, int $post_id): ?TestCompositeEntity
    {
        return $this->createEntity($this->_findOne(['user_id' => $user_id, 'post_id' => $post_id]));
    }

    /**
     * @return TestCompositeEntity[]
     */
    public function findAllByUser(int $userId): array
    {
        return $this->createEntities($this->_findAll(['user_id' => $userId, 'status' => TestUnitEnum::ACTIVE]));
    }

    public function countAllByUser(int $userId): int
    {
        return $this->_countAll(['user_id' => $userId]);
    }

    /**
     * @param array $ids
     * @return TestCompositeEntity[]
     * @throws \Composite\DB\Exceptions\DbException
     */
    public function findMulti(array $ids): array
    {
        return $this->createEntities($this->_findMulti($ids), 'post_id');
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
                `status` VARCHAR(16) DEFAULT 'ACTIVE' NOT NULL,
                `created_at` TIMESTAMP NOT NULL,
                CONSTRAINT TestComposite PRIMARY KEY (`user_id`, `post_id`)
            );
            "
        );
        return true;
    }

    public function truncate(): void
    {
        $this->getConnection()->executeStatement("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}