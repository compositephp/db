# Migrations

> **_NOTE:_**  This is experimental feature

Migrations enable you to maintain your database schema within your PHP entity classes. 
Any modification made in your class triggers the generation of migration files. 
These files execute SQL queries which synchronize the schema from the PHP class to the corresponding SQL table. 
This mechanism ensures consistent alignment between your codebase and the database structure.

## Supported Databases
- MySQL
- Postgres (Coming soon)
- SQLite (Coming soon)

## Getting Started

To begin using migrations you need to add Composite Sync package into your project and configure it:

### 1. Install package via composer:
 ```shell
 $ composer require compositephp/sync
 ```
### 2. Configure connections
You need to configure ConnectionManager, see instructions [here](configuration.md)

### 3. Configure commands

Add [symfony/console](https://symfony.com/doc/current/components/console.html) commands to your application:
- Composite\Sync\Commands\GenerateEntityCommand
- Composite\Sync\Commands\GenerateTableCommand

Here is an example of a minimalist, functional PHP file:

```php
<?php declare(strict_types=1);
include 'vendor/autoload.php';

use Composite\Sync\Commands;
use Symfony\Component\Console\Application;

//may be changed with .env file
putenv('CONNECTIONS_CONFIG_FILE=/path/to/your/connections/config.php');

$app = new Application();
$app->addCommands([
     new Commands\GenerateEntityCommand(),
     new Commands\GenerateTableCommand(),
]);
$app->run();
```
## Available commands

* ### composite:generate-entity

The command examines the specific SQL table and generates an [Composite\Entity\AbstractEntity](https://github.com/compositephp/entity) PHP class.
This class embodies the table structure using native PHP syntax, thereby representing the original SQL table in a more PHP-friendly format.

```shell
php cli.php composite:generate-entity connection_name TableName 'App\Models\EntityName'
```

| Argument   | Required | Description                                          |
|------------|----------|------------------------------------------------------|
| connection | Yes      | Name of connection from connection config file       |
| table      | Yes      | Name of SQL table                                    |
| entity     | Yes      | Full classname of the class that needs to be created |

Options:

| Option  | Description             |
|---------|-------------------------|
| --force | Overwrite existing file |

* ### composite:generate-table

The command examines the specific Entity and generates a [Table](https://github.com/compositephp/db) PHP class.
This class acts as a gateway to a specific SQL table, providing user-friendly CRUD tools for interacting with SQL right off the bat.

```shell
php cli.php composite:generate-table 'App\Models\EntityName'
```

| Argument  | Required | Description                                   |
|-----------|----------|-----------------------------------------------|
| entity    | Yes      | Full Entity classname                         |
| table     | No       | Full Table classname that needs to be created |

Options:

| Option   | Description                                |
|----------|--------------------------------------------|
| --cached | Generate cached version of PHP Table class |
| --force  | Overwrite existing file                    |
