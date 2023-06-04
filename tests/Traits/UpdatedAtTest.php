<?php declare(strict_types=1);

namespace Composite\DB\Tests\Traits;

use Composite\DB\Tests\TestStand\Entities\TestUpdatedAtEntity;
use Composite\DB\Tests\TestStand\Tables\TestUpdateAtTable;

final class UpdatedAtTest extends \PHPUnit\Framework\TestCase
{
    public function test_trait(): void
    {
        $entity = new TestUpdatedAtEntity('John');
        $this->assertNull($entity->updated_at);

        $table = new TestUpdateAtTable();
        $table->save($entity);

        $this->assertNotNull($entity->updated_at);

        $dbEntity = $table->findByPk($entity->id);
        $this->assertNotNull($dbEntity);

        $this->assertEquals($entity->updated_at, $dbEntity->updated_at);


        $entity->name = 'Richard';
        $table->save($entity);

        $this->assertNotEquals($entity->updated_at, $dbEntity->updated_at);
        $lastUpdatedAt = $entity->updated_at;

        //should not update entity
        $table->save($entity);
        $this->assertEquals($lastUpdatedAt, $entity->updated_at);
    }
}