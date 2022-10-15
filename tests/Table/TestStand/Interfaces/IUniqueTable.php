<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table\TestStand\Interfaces;

use Composite\DB\Tests\Table\TestStand\Entities\TestCompositeEntity;
use Composite\DB\Tests\Table\TestStand\Entities\TestUniqueEntity;

interface IUniqueTable
{
    public function findByPk(string $id): ?TestUniqueEntity;
    /**
     * @return TestCompositeEntity[]
     */
    public function findAllByName(string $name): array;
    public function countAllByName(string $name): int;
    public function truncate(): void;
}