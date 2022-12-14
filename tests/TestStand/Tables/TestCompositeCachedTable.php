<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Tables;

use Composite\DB\TableConfig;
use Composite\DB\Tests\TestStand\Entities\TestCompositeEntity;
use Composite\DB\Tests\TestStand\Interfaces\ICompositeTable;
use Composite\Entity\AbstractEntity;

class TestCompositeCachedTable extends \Composite\DB\AbstractCachedTable implements ICompositeTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(TestCompositeEntity::schema());
    }

    protected function getFlushCacheKeys(TestCompositeEntity|AbstractEntity $entity): array
    {
        return [
            $this->getListCacheKey('user_id = :user_id', ['user_id' => $entity->user_id]),
            $this->getCountCacheKey('user_id = :user_id', ['user_id' => $entity->user_id]),
        ];
    }

    public function findOne(int $user_id, int $post_id): ?TestCompositeEntity
    {
        return $this->createEntity($this->findOneCachedInternal([
            'user_id' => $user_id,
            'post_id' => $post_id,
        ]));
    }

    /**
     * @return TestCompositeEntity[]
     */
    public function findAllByUser(int $userId): array
    {
        return array_map(
            fn (array $data) => TestCompositeEntity::fromArray($data),
            $this->findAllCachedInternal(
                'user_id = :user_id',
                ['user_id' => $userId],
            )
        );
    }

    public function countAllByUser(int $userId): int
    {
        return $this->countAllCachedInternal(
            'user_id = :user_id',
            ['user_id' => $userId],
        );
    }

    public function truncate(): void
    {
        $this->getConnection()->executeStatement("DELETE FROM {$this->getTableName()} WHERE 1");
    }
}