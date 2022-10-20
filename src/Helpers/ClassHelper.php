<?php declare(strict_types=1);

namespace Composite\DB\Helpers;

class ClassHelper
{
    public static function extractNamespace(string $name): string
    {
        return ($pos = strrpos($name, '\\')) ? substr($name, 0, $pos) : '';
    }

    public static function extractShortName(string $name): string
    {
        return ($pos = strrpos($name, '\\')) === false
            ? $name
            : substr($name, $pos + 1);
    }
}