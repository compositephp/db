# What is Composite DB

Composite DB is modern and light PHP ORM, DataMapper which allows you to represent your SQL tables schme in OOP style using full 
power of PHP 8.1+ class syntax. It also gives you CRUD, query builder and automatic caching out of the box, so you can start
to work with your database from php code in a minutes!

## Features

* Uses full power of PHP 8.1+ class syntax to represent SQL table schema
* Automatic caching out the box (redis, memcache, filecache, etc)
* Serialization entity to json or flat array with scalar values
* Deserialization from array to entity with auto-casting to smart types (DateTime, Enums, etc.)
* Generates code from SQL tables schema
* Automatic migration generator
* Supports all popular SQL drivers (MySQL, SQLite, Postgres, MicrosoftSQL)
* Query builder
* Lightweight and fast

## Why do I need this, if there is Doctrine or Cycle ORM?

Composite DB designed as fast and easy to use alternative of popular ORM's. In synthetic CRUD test its 1.5x faster than
Doctrine or Cycle ORM in pure SQL queries mode and many times faster in automatic-cache mode.
Additionally, it provides more typed structure so you will not need PHPdoc to help your IDE.

But there is consequence of such speed, it's quite difficult to combine automatic-cache and foreign keys so Composite DB
doesn't support joins or any relations between SQL tables.

### When to use Composite DB

* You have many simple SQL tables
* You want to cache query results and make it easy
* You are fine to make 2 cached selects instead of using SQL "JOINS"
* You want typed interfaces out of the box to help your IDE
* You tired to write the code and want to generate it

### When to use alternatives

You have complex and branched structure of tables in your database and you 100% sure in your indexes and 
fully trust JOIN performance.

## Requirements

* PHP 8.1+
* PDO Extension with desired database drivers

## Installation

1. Install package via composer:
    ```shell
    $ composer require compositephp/db
    ```
2. Configure DatabaseManager from [cycle/database](https://github.com/cycle/database) package
3. (Optional) Configure [symfony/console](https://symfony.com/doc/current/components/console.html#creating-a-console-application) commands to use automatic class generators
4. (Optional) Install and configure any PSR-16 (simple cache) package to use automatic caching

Full configuration working example can be found [here](./doc/configuration.md).

## Quick example
Imagine you have simple table `Users`

```mysql
create table Users
(
    `id`         int auto_increment,
    `email`      varchar(255)                                         not null,
    `name`       varchar(255)               default NULL              null,
    `is_test`    tinyint(1)                 default 0                 not null,
    `status`     enum ("ACTIVE", "BLOCKED") default "ACTIVE"          null,
    `created_at` TIMESTAMP                  default CURRENT_TIMESTAMP not null,
    constraint Users_pk primary key (id)
);
```

First, you need to do is to execute command to generate php entity:

```shell
$ php console.php composite-db:generate-entity dbName Users 'App\User'
```

New entity class `User` and enum `Status` will be automatically generated:

```php
#[Table(db: 'dbName', name: 'Users')]
class User extends AbstractEntity
{
    #[PrimaryKey(autoIncrement: true)]
    public readonly int $id;

    public function __construct(
        public string $email,
        public ?string $name = null,
        public bool $is_test = false,
        public Status $status = Status::ACTIVE,
        public readonly \DateTimeImmutable $created_at = new \DateTimeImmutable(),
    ) {}
}
```

```php
enum Status
{
    case ACTIVE;
    case BLOCKED;
}
```

Second step, is to generate a table class (repository) for your entity:

```shell
$ php console.php composite-db:generate-table 'App\User' 'App\UsersTable'
```

And that's it, now you have CRUD for your SQL table and simple selects:

```php
$table = new UsersTable(...);

//Create
$user = new User(
    email: 'user@example.com',
    name: 'John',
);
$table->save($user);

//Read
$user = $table->findByPk(123);

//Update
$user->status = Status::BLOCKED;
$table->save($user);

//Delete
$table->delete($user);

//Other selects out of the box
$table->findAll();
$table->countAll();
```

## Documentation

1. Basics
   - [Entity](doc/entity.md)
   - [Table](doc/table.md)
2. [Configuration](doc/configuration.md)
3. [Automatic Caching](doc/cache.md)
4. [Code generators](doc/code-generators.md)
5. [Migrations](doc/migrations.md)
6. More coming soon

## License:

MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information. Maintained by Composite PHP.
