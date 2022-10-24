<?php declare(strict_types=1);

namespace Composite\DB\Entity\Traits;

trait OptimisticLock
{
    public int $version = 1;
}
