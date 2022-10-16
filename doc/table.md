# Table

Overview:
* [Basics](#basics)
* [Custom queries](#custom-queries)
* [Transactions](#transactions)
* [Automatic cache](cache.md)

## Basics

In Composite DB the `AbstractTable` is query class that must be the only entry point to work with your SQL Table.

Before using it, you need to [configure DatabaseManager](configuration.md#configure-databasemanager)

By default, `AbstractTable` has CRUD public methods out of the box:
* `save()` - insert or update entity
* `saveMany()` - insert or update many entities
* `delete()` - delete entity from table
* `deleteMany()`- delete many entities

So you only need to implement select queries to your table:

Example:

```php
use Composite\DB\AbstractTable;
use Composite\DB\Entity\Schema;

class UsersTable extends AbstractTable
{
    protected static function getSchema(): Schema
    {
        return User::schema();
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
The heart of Composite DB is [cycle/database](https://github.com/cycle/database) query builder, please read 
documentation and use it to make custom queries.

Example with internal helper:
```php
/**
 * @return User[]
 */
public function findAllActiveAdults(): array
{
    $rows = $this->findAllInternal([
        'age' => ['>', 18],
        'status' => Status::ACTIVE->name,
    ]);
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
        ->fetchAll();
    return $this->createEntities($rows);
}
```

## Transactions

To wrap you operations in 1 transactions there are 2 ways:
1. Use internal table class method `transaction()` if you are working only with 1 table.
2. Use class `Composite\DB\CombinedTransaction` if you need to work with several tables in 1 transaction.

   ```php
   $usersTable = new UsersTable(...);
   $photosTable = new PhotosTable(...);
    
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