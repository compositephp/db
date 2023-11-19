<?php declare(strict_types=1);

namespace Composite\DB\Tests\TestStand\Entities\Enums;

use Composite\DB\Attributes\{PrimaryKey};
use Composite\DB\Attributes\Table;

enum TestUnitEnum
{
    case ACTIVE;
    case DELETED;
}