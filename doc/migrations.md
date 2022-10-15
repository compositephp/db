# Migrations

> **_NOTE:_**  This is experimental feature

Migrations based on [cycle/migrations](https://github.com/cycle/migrations) package.

First you need to [configure migrations](configuration.md#migrations)

Then you can use console command:

```shell
$ php console.php composite-db:migrate
```

Arguments:
1. `entity` (Optional) -  if you want to generate migration for particular Entity instead of scanning project folder.
2. `--generate` - if you want to only generate migrations and not apply them
3. `--migrate` - if you want to only apply migrations, skipping step with generation