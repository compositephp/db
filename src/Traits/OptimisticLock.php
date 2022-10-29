<?php declare(strict_types=1);

namespace Composite\DB\Traits;

trait OptimisticLock
{
    public int $version = 1;
}
