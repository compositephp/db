<?php declare(strict_types=1);

namespace Composite\DB\Traits;

trait SoftDelete
{
    protected ?\DateTimeImmutable $deleted_at = null;

    public function delete(\DateTimeImmutable $dateTime = new \DateTimeImmutable()): void
    {
        $this->deleted_at = $dateTime;
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
