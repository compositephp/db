# Table

Overview:
* [Basics](#basics)
* [Custom queries](#custom-queries)
* [Transactions](#transactions)
* [Concurrency transactions and locks](#locks)
* [Automatic cache](cache.md)

## Basics

In Composite DB `AbstractTable` is [Table Gateway](https://www.martinfowler.com/eaaCatalog/tableDataGateway.html) which
holds all the SQL for accessing a single table or view: selects, inserts, updates, and deletes. Other code calls its 
methods for all interaction with the database.

Before using it, you need to [configure ConnectionManager](configuration.md#configure-connectionmanager)

By default, `AbstractTable` has CRUD public methods out of the box:
* `save()` - insert or update entity
* `saveMany()` - insert or update many entities
* `delete()` - delete entity from table
* `deleteMany()`- delete many entities

So you only need to implement select queries to your table:

Example:

```php
use Composite\DB\AbstractTable;
use Composite\DB\TableConfig;

class UsersTable extends AbstractTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(User::schema());
    }

    public function findOne(int $id): ?User
    {
        return $this->createEntity($this->findOneInternal($id));
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return $this->createEntities($this->findAllInternal());
    }

    public function countAll(): int
    {
        return $this->countAllInternal();
    }
}
```

## Custom queries
The heart of Composite DB is [doctrine/dbal](https://github.com/doctrine/dbal) query builder, please read 
documentation and use it to make custom queries.

Example with internal helper:
```php
/**
 * @return User[]
 */
public function findAllActiveAdults(): array
{
    $rows = $this->findAllInternal(
        'age > :age AND status = :status',
        ['age' => 18, 'status' => Status::ACTIVE->name],
    );
    return $this->createEntities($rows);
}
```

Example with pure query builder
```php
/**
 * @return User[]
 */
public function findCustom(): array
{
     $rows = $this
         ->select()
         ->where(...)
         ->orWhere(...)
         ->orderBy(...)
         ->fetchAllAssociative();
     return $this->createEntities($rows);
}
```

## Transactions

To wrap you operations in 1 transactions there are 2 ways:
1. Use internal table class method `transaction()` if you are working only with 1 table.
2. Use class `Composite\DB\CombinedTransaction` if you need to work with several tables in 1 transaction.

   ```php
   $usersTable = new UsersTable();
   $photosTable = new PhotosTable();
    
   $transaction = new CombinedTransaction();
   
   $user = new User(...);
   $transaction->save($usersTable, $user);
   
   $photo = new Photo(
       user_id: $user->id, 
       ...
   );
   $transaction->save($photosTable, $photo);
   $transaction->commit();
   ```
   
## Locks
If you worry about concurrency updates during your transaction and want to be sure that only 1 process changing your 
data at one time you can use optimistic or pessimistic lock.

### 1. Optimistic lock
Add trait `Composite\DB\Traits\OptimisticLock` to your entity and column `version` (INT NOT NULL DEFAULT 1) to 
your table.

### 2. Pessimistic lock
You need to setup PSR-16 (simple cache) interface and call `CombinedTransaction::lock()`.

As lock key parts use something specific related to your operation and another concurrency process will wait. 

   ```php
   $transaction = new CombinedTransaction();
   
   //throws Exception if failed to get lock
   $transaction->lock($psr16CacheInterface, ['user', 123, 'photos', 'update']);
   
   $transaction->save(...);
   $transaction->save(...);
   $transaction->save(...);
   
   $transaction->commit();
   ```