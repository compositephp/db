# Automatic cache

To start using auto-cache feature you need:
1. Setup any psr-16 cache implementation.
2. Change parent class of your [Table](table.md) from `Composite\DB\AbstractTable` 
to `Composite\DB\AbstractCachedTable`
3. Implement method `getFlushCacheKeys()`
4. Change all internal select methods to their cached versions (example: `findByPkInternal()` 
to `_findByPkCached()` etc.)

You can also generate cached version of your table with console command:

```shell
$ php console.php composite-db:generate-table 'App\User' 'App\UsersTable' --cached
```

## Configuration

For using automatic caching you need to have configured and initialized any [PSR-16](https://www.php-fig.org/psr/psr-16/) package.
If you already have one - just pass cache instance to your `Composite\DB\AbstractCachedTable`,
if not - use any from [GitHub search](https://github.com/search?q=psr-16)

Below you can find example with simple file-based caching package [kodus/file-cache](https://github.com/kodus/file-cache).

 ```shell
 $ composer require kodus/file-cache
 ```

 ```php
 $cache = new \Kodus\Cache\FileCache('/path/to/your/cache/dir', 3600);
 ```

## What is `getFlushCacheKeys()` for

This method is very important, it triggers every time before saving changed entity and inside of it you must define 
all cache keys of lists or count() results queries where this entity participates.

Example: imagine you have table with some posts and you want to cache featured posts 
```php
class PostsTable extends AbstractCachedTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(Post::schema());
    }

    public function findByPk(int $id): ?Post
    {
        return $this->createEntity($this->_findByPkCached($id));
    }
    
    /**
    * @return Post[]
    */
    public function findAllFeatured(): array
    {
        return $this->createEntities($this->_findAll(
            'is_featured = :is_featured',
            ['is_featured' => true],
        ));
    }
    
    public function countAllFeatured(): int
    {
        return $this->_countAllCached(
            'is_featured = :is_featured',
            ['is_featured' => true],
        );
    }
    
    public getFlushCacheKeys(AbstractEntity|Post $entity): array
    {
        // to avoid unnecessary cache flushing
        // its better to check that changed post is featured or it was
        if ($entity->is_featured || $entity->getOldValue('is_featured') === true) {
            return [
                $this->getListCacheKey(
                    'is_featured = :is_featured',
                    ['is_featured' => true],
                ),
                $this->getCountCacheKey(
                    'is_featured = :is_featured',
                    ['is_featured' => true],
                ),
            ];        
        }
        return [];
    }
}
```

