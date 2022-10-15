<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Interfaces;

use Composite\DB\Tests\Table\TestStand\Entities\TestAutoincrementEntity;
use Composite\DB\Tests\Table\TestStand\Entities\TestCompositeEntity;

interface IAutoincrementTable
{
    public function findByPk(int $id): ?TestAutoincrementEntity;
    public function findOneByName(string $name): ?TestAutoincrementEntity;
    /**
     * @return TestAutoincrementEntity[]
     */
    public function findAllByName(string $name): array;
    public function countAllByName(string $name): int;
    public function truncate(): void;
}