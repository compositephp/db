# Configuration

Overview:
* [Configure ConnectionManager](#configure-connectionmanager)
* [Configure console commands](#configure-console-commands)
* [Configure code generators](#code-generators)


## Configure ConnectionManager

ConnectionManager must be configured before using the table classes and code generators.

1. Create file which returns named connection params, format from 
[doctrine DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#configuration):

   ```php
   <?php declare(strict_types=1);
   return [
        'sqlite' => [
            'driver' => 'pdo_sqlite',
            'path' => __DIR__ . '/tests/runtime/sqlite/database.db',
        ],
        'mysql' => [
            'driver' => 'pdo_mysql',
            'dbname' => 'test',
            'user' => 'test',
            'password' => 'test',
            'host' => '127.0.0.1',
        ],
        'postgres' => [
            'driver' => 'pdo_pgsql',
            'dbname' => 'test',
            'user' => 'test',
            'password' => 'test',
            'host' => '127.0.0.1',
        ],
   ];
   ```
2. Setup `CONNECTIONS_CONFIG_FILE` environment variable with path to  your config file:
   * Use [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) package and `.env` file
   * Or simply call: 
     ```php 
     putenv('CONNECTIONS_CONFIG_FILE=/path/to/config/file.php')
     ```

## Configure console commands

To use code generators and migrations you need to configure [symfony/console](https://symfony.com/doc/current/components/console.html)
component and add composite-db commands into it. If you already using symfony console, just add commands that you need.

### Code generators

Composite DB has next code generators:
* `Composite\DB\Commands\GenerateEntityCommand` for Entity class and Enums generating
* `Composite\DB\Commands\GenerateTableCommand` for Table class generating

```php
<?php

use Composite\DB\Commands;
use Symfony\Component\Console\Application;

$app = new Application();
$app->addCommands([
    new Commands\GenerateEntityCommand(), //to initialize $dbManager see example above
    new Commands\GenerateTableCommand(),
]);
$app->run();
```