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
        return $this->_findByPk($id);
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return $this->_findAll();
    }

    public function countAll(): int
    {
        return $this->_countAll();
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
    return $this->_findAll(
        new Where(
            'age > :age AND status = :status',
            ['age' => 18, 'status' => Status::ACTIVE->name],
        )
    );
}
```

Or it might be simplified to:
```php
/**
 * @return User[]
 */
public function findAllActiveAdults(): array
{
    return $this->_findAll([
        'age' => ['>', 18],
        'status' => Status:ACTIVE,
    ]);
}
```

Or you can use standard Doctrine QueryBuilder
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
In order to encapsulate your operations within a single transaction, you have two strategies at your disposal:
1. Use the internal table class method transaction() if your operations are confined to a single table.
2. Use the Composite\DB\CombinedTransaction class if your operations involve multiple tables within a single transaction.

Below is a sample code snippet illustrating how you can use the CombinedTransaction class:

   ```php
   // Create instances of the tables you want to work with
   $usersTable = new UsersTable();
   $photosTable = new PhotosTable();
   
   // Instantiate the CombinedTransaction class
   $transaction = new CombinedTransaction();
   
   // Create a new user and add it to the users table within the transaction
   $user = new User(...);
   $transaction->save($usersTable, $user);
   
   // Create a new photo associated with the user and add it to the photos table within the transaction
   $photo = new Photo(
       user_id: $user->id, 
       ...
   );
   $transaction->save($photosTable, $photo);
   
   // Commit the transaction to finalize the changes
   $transaction->commit();
   ```

Remember, using a transaction ensures that your operations are atomic. This means that either all changes are committed to the database, or if an error occurs, no changes are made.
   
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