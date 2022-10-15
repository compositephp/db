# Configuration

Overview:
* [Configure DatabaseManager](#configure-databasemanager)
* [Configure console commands](#configure-console-commands)
* [Configure code generators](#code-generators)
* [Configure migrations](#migrations)


## Configure DatabaseManager

Every [Table class](table.md) requires `DatabaseManager` instance to be passed in their constructor. 
For detailed information please visit [official cycle/database documentation](https://cycle-orm.dev/docs/database-connect/2.x/en). 

Example:
```php
<?php

use Cycle\Database\Config;
use Cycle\Database\DatabaseManager;

$dbManager = new DatabaseManager(new Config\DatabaseConfig([
    'databases' => [
        'mysql' => ['connection' => 'mysql'],
    ],
    'connections' => [
        'mysql' => new Config\MySQLDriverConfig(
            new Config\MySQL\TcpConnectionConfig(
                database: 'dbName',
                host: 'localhost',
                port: 3306,
                user: 'username',
                password: 'password',
            ),
        ),
    ],
]));
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

use Cycle\Database\DatabaseManager;
use Composite\DB\Commands;
use Symfony\Component\Console\Application;

$dbManager = new DatabaseManager(...); // see example above

$app = new Application();
$app->addCommands([
    new Commands\GenerateEntityCommand($dbManager), //to initialize $dbManager see example above
    new Commands\GenerateTableCommand(),
]);
$app->run();
```

### Migrations

Composite DB uses `cycle/migrations` component for migrations. For more information please visit [official cycle/migrations documentation](https://cycle-orm.dev/docs/database-migrations/2.x/en).

```php
<?php

use Cycle\Database\DatabaseManager;
use Cycle\Migrations\Config\MigrationConfig;
use Composite\DB\Commands;
use Spiral\Tokenizer;
use Symfony\Component\Console\Application;

$dbManager = new DatabaseManager(...); // see example above

$migrationConfig = new MigrationConfig([
    'directory' => '/path/to/your/migrations', // where to store migrations
    'table'     => '__migrations',             // database table to store migration status
    'safe'      => true                        // When set to true no confirmation will be requested on migration run.
]);
$tokenizer = new Tokenizer\Tokenizer(
    new Tokenizer\Config\TokenizerConfig([
        'directories' => [
            '/path/to/your/source/code1', // directories which should be scanned
            '/path/to/your/source/code2', // to find all Entity classes and generate migrations
        ],  
    ])
);
$app = new Application();
$app->addCommands([
    new Commands\MigrateCommand($dbManager, $migrationConfig, $tokenizer),
]);
$app->run();
```