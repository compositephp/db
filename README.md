# What is Composite DB

Composite DB is light and fast PHP ORM, DataMapper which allows you to represent your SQL tables schme in OOP style using full 
power of PHP 8.1+ class syntax. 

It also gives you CRUD, query builder and automatic caching out of the box, so you can start
to work with your database from php code in a minutes!

Overview:
* [Mission](#mission)
* [Requirements](#requirements)
* [Installation](#installation)
* [Quick example](#quick-example)

## Mission
You probably may ask, why do you need another ORM if there are already popular Doctrine, CycleORM, etc.?

Composite DB solves multiple problems:

* **Lightweight** - easier entity schema, no getters and setters, you don't need attributes for each column definition, 
just use native php class syntax.
* **Speed** - it's 1.5x faster in pure SQL queries mode and many times faster in automatic caching mode.
* **Easy caching** - gives you CRUD operations caching out of the box and in general its much easier to work with cached "selects".
* **Mixed return types** - Composite DB forces you to be more strict typed and makes your IDE happy.
* **Hydration** - you can serialize your Entities to plain array or json and deserialize them back.
* **Flexibility** - gives you more freedom to extend Repositories, for example its easier to build sharding tables.
* **Code generation** - you can generate Entity and Repository classes from your SQL tables.
* **Division of responsibility** - there is no "god" entity manager, every Entity has its own Repository class and its the only entry point to make queries to your table.

But there is 1 sacrifice for all these features - there is no support for relations in Composite DB. Its too much 
uncontrollable magic and hidden bottlenecks with "JOINs" and its not possible to implement automatic caching with 
relations. We recommend to have full control and make several cached select queries instead of "JOINs".

### When you shouldn't use Composite DB

1. If you have intricate structure with many foreign keys in your database 
2. You 100% sure in your indexes and fully trust "JOINs" performance
3. You dont want to do extra cached select queries and want some magic

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

> You can find full working example [here](doc/example.md) which you can copy and run as is.

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
