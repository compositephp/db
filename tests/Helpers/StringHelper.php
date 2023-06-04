<?php declare(strict_types=1);

namespace Composite\DB\Tests\Helpers;

class StringHelper
{
    public static function getUniqueName(): string
    {
        return (new \DateTime())->format('U') . '_' . uniqid();
    }
}