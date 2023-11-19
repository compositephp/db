# Example

This is complete working example, you can copy and run it as is.

All you need is [pdo_sqlite](https://www.php.net/manual/en/ref.pdo-sqlite.php) extension in your php and installed app via composer.

```php
<?php declare(strict_types=1);
include 'vendor/autoload.php';

use Composite\DB\ConnectionManager;
use Composite\Entity\AbstractEntity;
use Composite\DB\Attributes\{Table, PrimaryKey};
use Composite\DB\TableConfig;

#[Table(connection: 'sqlite', name: 'Users')]
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

enum Status
{
    case ACTIVE;
    case BLOCKED;
}

class UsersTable extends \Composite\DB\AbstractTable
{
    protected function getConfig(): TableConfig
    {
        return TableConfig::fromEntitySchema(User::schema());
    }

    public function findByPk(int $id): ?User
    {
        return $this->_findByPk($id);
    }

    /**
     * @return User[]
     */
    public function findAllActive(): array
    {
        return $this->_findAll(['status' => Status::ACTIVE]);
    }

    public function countAllActive(): int
    {
        return $this->_countAll(
            ['status' => Status::ACTIVE],
        );
    }

    public function init(): void
    {
        $this->getConnection()->executeStatement("
            CREATE TABLE IF NOT EXISTS Users
            (
                `id`         INTEGER
                    CONSTRAINT Users_pk PRIMARY KEY AUTOINCREMENT,
                `email`      VARCHAR(255)                           NOT NULL,
                `name`       VARCHAR(255) DEFAULT NULL,
                `is_test`    INT          DEFAULT 0                 NOT NULL,
                `status`     ENUM         DEFAULT 'ACTIVE'          NOT NULL,
                `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP NOT NULL,
                check (\"status\" IN ('ACTIVE', 'BLOCKED'))
            );
        ");
    }
}

ConnectionManager::configure([
    'sqlite' => [
        'driver' => 'pdo_sqlite',
        'path' => __DIR__ . '/database.db'
    ],
]);

$table = new UsersTable();
$table->init();

//Create
$user = new User(
    email: 'user@example.com',
    name: 'John',
);
$table->save($user);

echo "New inserted user: " .PHP_EOL;
echo json_encode($user, JSON_PRETTY_PRINT) . PHP_EOL;

echo "Total active users: " . $table->countAllActive() . PHP_EOL;

echo "All active users:" . PHP_EOL;
var_dump($table->findAllActive());

//Read
$foundUser = $table->findByPk($user->id);

//Update
$foundUser->status = Status::BLOCKED;
$table->save($foundUser);

//Delete
$table->delete($foundUser);

echo "Total active users after delete: " . $table->countAllActive() . PHP_EOL;
```
