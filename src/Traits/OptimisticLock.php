<?php declare(strict_types=1);

namespace Composite\DB\Traits;

trait OptimisticLock
{
    protected int $lock_version = 1;

    public function getVersion(): int
    {
        return $this->lock_version;
    }

    public function incrementVersion(): void
    {
        $this->lock_version++;
    }
}
