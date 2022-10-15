# Code generators

Before start, you need to [configure](configuration.md#configure-console-commands) code generators.

## Entity class generator
Arguments: 
1. `db` - DatabaseManager database name
2. `table` - SQL table name
3. `entity` - Full classname of new entity
4. `--force` - option if existing file should be overwritten

Example:
```shell
$ php console.php composite-db:generate-entity dbName Users 'App\User' --force
```

## Table class generator
Arguments:
1. `entity` - Entity full class name
2. `table` - Table full class name
3. `--cached` - Option if cached version of table class should be generated
4. `--force` - Option if existing file should be overwritten

Example:
```shell
$ php console.php composite-db:generate-table 'App\User' 'App\UsersTable'
```