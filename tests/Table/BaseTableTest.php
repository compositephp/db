<?php declare(strict_types=1);

namespace Composite\DB\Tests\Table;

use Kodus\Cache\FileCache;
use Psr\SimpleCache\CacheInterface;

abstract class BaseTableTest extends \PHPUnit\Framework\TestCase
{
    private static ?CacheInterface $cache = null;

    public static function getCache(): CacheInterface
    {
        if (self::$cache === null) {
            self::$cache = new FileCache(dirname(__DIR__) . '/runtime/cache', 3600);
        }
        return self::$cache;
    }

    protected function getUniqueName(): string
    {
        return (new \DateTime())->format('Uu') . '_' . uniqid();
    }
}