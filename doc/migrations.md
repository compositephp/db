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
- Composite\Sync\Commands\MigrateCommand
- Composite\Sync\Commands\MigrateNewCommand
- Composite\Sync\Commands\MigrateDownCommand
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
putenv('ENTITIES_DIR=/path/to/your/source/dir'); // e.g. "./src"
putenv('MIGRATIONS_DIR=/path/to/your/migrations/dir'); // e.g. "./src/Migrations"
putenv('MIGRATIONS_NAMESPACE=Migrations\Namespace'); // e.g. "App\Migrations"

$app = new Application();
$app->addCommands([
     new Commands\MigrateCommand(),
     new Commands\MigrateNewCommand(),
     new Commands\MigrateDownCommand(),
     new Commands\GenerateEntityCommand(),
     new Commands\GenerateTableCommand(),
]);
$app->run();
```
## Available commands

* ### composite:migrate

This command performs two primary functions depending on its usage context. Initially, when called for the first time,
it scans all entities located in the `ENTITIES_DIR` directory and generates migration files corresponding to these entities.
This initial step prepares the necessary migration scripts based on the current entity definitions. Upon its second
invocation, the command shifts its role to apply these generated migration scripts to the database. This two-step process
ensures that the database schema is synchronized with the entity definitions, first by preparing the migration scripts
and then by executing them to update the database.

```shell
php cli.php composite:migrate
```

| Option       | Short | Description                                               |
|--------------|-------|-----------------------------------------------------------|
| --connection | -c    | Check migrations for all entities with desired connection |
| --entity     | -e    | Check migrations only for entity class                    |
| --run        | -r    | Run migrations without asking for confirmation            |
| --dry        | -d    | Dry run mode, no real SQL queries will be executed        |

* ### composite:migrate-new

This command generates a new, empty migration file. The file is provided as a template for the user to fill with the
necessary database schema changes or updates. This command is typically used for initiating a new database migration,
where the user can define the specific changes to be applied to the database schema. The generated file needs to be
manually edited to include the desired migration logic before it can be executed with the migration commands.

```shell
php cli.php composite:migrate-new
```

| Argument    | Required | Description                              |
|-------------|----------|------------------------------------------|
| connection  | No       | Name of connection from your config file |
| description | No       | Short description of desired changes     |

* ### composite:migrate-down

This command rolls back the most recently applied migration. It is useful for undoing the last schema change made to
the database. This can be particularly helpful during development or testing phases, where you might need to revert
recent changes quickly.

```shell
php cli.php composite:migrate-down
```

| Argument   | Required | Description                                                               |
|------------|----------|---------------------------------------------------------------------------|
| connection | No       | Name of connection from your config file                                  |
| limit      | No       | Number of migrations should be rolled back from current state, default: 1 |


| Option | Short | Description                                         |
|--------|-------|-----------------------------------------------------|
| --dry  | -d    | Dry run mode, no real SQL queries will be executed  |

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

| Option  | Short | Description             |
|---------|-------|-------------------------|
| --force | -f    | Overwrite existing file |

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

| Option   | Short | Description                                |
|----------|-------|--------------------------------------------|
| --cached | -c    | Generate cached version of PHP Table class |
| --force  | -f    | Overwrite existing file                    |