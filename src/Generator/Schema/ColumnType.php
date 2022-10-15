<?php declare(strict_types=1);

namespace Composite\DB\Generator\Schema;

enum ColumnType: string
{
    case String = 'string';
    case Integer = 'int';
    case Float = 'float';
    case Boolean = 'bool';
    case Datetime = '\DateTimeImmutable';
    case Array = 'array';
    case Object = '\stdClass';
    case Enum = 'enum';
}