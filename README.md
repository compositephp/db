# What is Composite DB
[![Latest Stable Version](https://poser.pugx.org/compositephp/db/v/stable)](https://packagist.org/packages/compositephp/db)
[![Build Status](https://github.com/compositephp/db/actions/workflows/main.yml/badge.svg)](https://github.com/compositephp/db/actions)
[![Codecov](https://codecov.io/gh/compositephp/db/branch/master/graph/badge.svg)](https://codecov.io/gh/compositephp/db/)

Composite DB is lightweight and fast PHP DataMapper and Table Gateway which allows you to represent your SQL tables 
scheme in OOP style using full power of PHP 8.1+ class syntax. 

It also gives you CRUD, query builder and automatic caching out of the box, so you can start
to work with your database from php code in a minutes!

Overview:
* [Features](#features)
* [Requirements](#requirements)
* [Installation](#installation)
* [Quick example](#quick-example)
* [Documentation](doc/README.md)

## Features

* **Lightweight** - easier entity schema, no getters and setters, you don't need attributes for each column definition, 
just use native php class syntax.
* **Speed** - it's 1.5x faster in pure SQL queries mode and many times faster in automatic caching mode (see [benchmark](https://github.com/compositephp/php-orm-benchmark)).
* **Easy caching** - gives you CRUD operations caching out of the box and in general it's much easier to work with cached "selects".
* **Strict types** - Composite DB forces you to be more strict typed and makes your IDE happy.
* **Hydration** - you can serialize your Entities to plain array or json and deserialize them back.
* **Flexibility** - gives you more freedom to extend Repositories, for example it's easier to build sharding tables.
* **Code generation** - you can generate Entity and Repository classes from your SQL tables.
* **Division of responsibility** - every Entity has its own Repository class, and it's the only entry point to make queries to your table.

It also has many popular features such as:

* **Query Builder** - build your queries with constructor, based on [doctrine/dbal](https://github.com/doctrine/dbal)
* **Migrations** - synchronise your php entities with database tables

## Requirements

* PHP 8.1+
* PDO Extension with desired database drivers

## Installation

1. Install package via composer:
    ```shell
    $ composer require compositephp/db
    ```
2. Configure `Composite\DB\ConnectionManager` ([example](./doc/configuration.md#configure-connectionmanager))
3. (Optional) Configure [symfony/console](https://symfony.com/doc/current/components/console.html#creating-a-console-application) 
commands to use automatic class generators ([example](./doc/configuration.md#configure-console-commands))
4. (Optional) Install and configure any PSR-16 (simple cache) package to use automatic caching

## Quick example
Imagine you have simple table `Users`

```mysql
create table Users
(
    `id`         int auto_increment,
    `email`      varchar(255)                                         not null,
    `name`       varchar(255)               default NULL              null,
    `is_test`    tinyint(1)                 default 0                 not null,
    `languages`  json                       default '[]'              not null,
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
        public array $languages = [],
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
$table = new UsersTable();

//Create
$user = new User(
    email: 'user@example.com',
    name: 'John',
    languages: ['en', 'fr'],
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

You can also serialize user entity to array or json:

```php
var_export($user->toArray());

//will output
array (
  'id' => 123,
  'email' => 'user@example.com',
  'name' => 'John',
  'is_test' => false,
  'languages' => '["en","fr"]',
  'status' => 'BLOCKED',
  'created_at' => '2022-01-01 11:22:33.000000',
)
```

Or deserialize (hydrate) entity from array:

```php
$user = User::fromArray([
  'id' => 123,
  'email' => 'user@example.com',
  'name' => 'John',
  'is_test' => false,
  'languages' => '["en","fr"]',
  'status' => 'BLOCKED',
  'created_at' => '2022-01-01 11:22:33.000000',
]);
```

And that's it, no special getters or setters, no "behaviours" or extra code, smart entity casts everything automatically.
More about Entity and supported auto casting types you can find [here](doc/entity.md).

## License:

MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information. Maintained by Composite PHP.
