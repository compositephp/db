# Migrations

> **_NOTE:_**  This is experimental feature

Migrations used a bridge to [doctrine/migrations](https://github.com/doctrine/migrations) package.
If you are not familiar with it, please read documentation before using composite bridge.

1. Install package:
    ```shell
    $ composer require compositephp/doctrine-migrations
    ```

2. Configure bridge:
    ```php
    $bridge = new \Composite\DoctrineMigrations\SchemaProviderBridge(
        entityDirs: [
           '/path/to/your/src', //path to your source code, where bridge will search for entities
        ],
        connectionName: 'sqlite', //only entities with this connection name will be affected 
        connection: $connection, //Doctrine\DBAL\Connection instance 
    ); 
    ```
   
3. Inject bridge into `\Doctrine\Migrations\DependencyFactory` as `\Doctrine\Migrations\Provider\SchemaProvider`
instance.
    ```php
    $dependencyFactory->setDefinition(SchemaProvider::class, static fn () => $bridge);
    ```

Full example:
```php
<?php declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\Migrations\Tools\Console\Command;
use Symfony\Component\Console\Application;

include __DIR__ . '/vendor/autoload.php';

$connection = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'dbname' => 'test',
    'user' => 'test',
    'password' => 'test',
    'host' => '127.0.0.1',
]);

$configuration = new Configuration();

$configuration->addMigrationsDirectory('Composite\DoctrineMigrations\Tests\runtime\migrations', __DIR__ . '/tests/runtime/migrations');
$configuration->setAllOrNothing(true);
$configuration->setCheckDatabasePlatform(false);

$storageConfiguration = new TableMetadataStorageConfiguration();
$storageConfiguration->setTableName('doctrine_migration_versions');

$configuration->setMetadataStorageConfiguration($storageConfiguration);

$dependencyFactory = DependencyFactory::fromConnection(
    new ExistingConfiguration($configuration),
    new ExistingConnection($connection)
);

$bridge = new \Composite\DoctrineMigrations\SchemaProviderBridge(
    entityDirs: [
        __DIR__ . '/src',
    ],
    connectionName: 'mysql',
    connection: $connection,
);
$dependencyFactory->setDefinition(SchemaProvider::class, static fn () => $bridge);

$cli = new Application('Migrations');
$cli->setCatchExceptions(true);

$cli->addCommands(array(
    new Command\DumpSchemaCommand($dependencyFactory),
    new Command\ExecuteCommand($dependencyFactory),
    new Command\GenerateCommand($dependencyFactory),
    new Command\LatestCommand($dependencyFactory),
    new Command\ListCommand($dependencyFactory),
    new Command\MigrateCommand($dependencyFactory),
    new Command\DiffCommand($dependencyFactory),
    new Command\RollupCommand($dependencyFactory),
    new Command\StatusCommand($dependencyFactory),
    new Command\SyncMetadataCommand($dependencyFactory),
    new Command\VersionCommand($dependencyFactory),
));

$cli->run();
```