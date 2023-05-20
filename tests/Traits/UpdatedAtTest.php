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
        $table->init();
        $table->save($entity);

        $this->assertNotNull($entity->updated_at);

        $dbEntity = $table->findByPk($entity->id);
        $this->assertNotNull($dbEntity);

        $this->assertEquals($entity->updated_at, $dbEntity->updated_at);
    }
}