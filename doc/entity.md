# Entity
Entity is main data particle of Composite DB package, and it can be of 2 types.

### 1. Simple data Entity
If you tired of unpredictable and untyped associative arrays - use Entity as smart object that can be serialized into
json or deserialized back.

Example:
```php
class Job extends AbstractEntity
{
    public function __construct(
        public readonly string $id,
        public readonly int $user_id,
        public readonly int $photo_id,
        public readonly bool $is_dry = false,
        public Status $status = Status::PROCESSING,
        public readonly \DateTime $scheduled_at = new \DateTime(),
    ) {}
}
```

You can call `toArray()` on such object, and it will be converted to plain array:
```php
array(
    "id" => "123abc",
    "user_id" => 123,
    "photo_id" => 456,
    "is_dry" => false,
    "status" => "PROCESSING",
    "scheduled_at" => "2022-01-01 00:00:01",
)
```

Or you can `json_encode()` such object and will get:

```json
{
  "id": "123abc",
  "user_id": 123,
  "photo_id": 456,
  "is_dry": false,
  "status": "PROCESSING",
  "scheduled_at": "2022-01-01 00:00:01"
}
```

To deserialize use static method `fromArray()`
```php
$job = Job::fromArray([
    "id" => "123abc",
    "user_id" => 123,
    "photo_id" => 456,
    "is_dry" => false,
    "status" => "PROCESSING",
    "scheduled_at" => "2022-01-01 00:00:01",
]);
```

### 2. Table data Entity
Has all simple entity features and additionally represents one of yours SQL table scheme via native php class 
syntax. Designed to work together with [Table class](table.md).

You need to add `#[Table]` and `#[PrimaryKey]` attributes to the class to define source of the table and primary 
key columns.

MySQL table:
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
Table Entity:

```php
use Composite\DB\Attributes\{Table, PrimaryKey};

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

## Supported column types:

Entity can automatically serialize and deserialize back almost every data types you may need and you don't need to 
write any code for it:

- String
- Integer
- Float
- Bool
- Array
- Object (strClass)
- DateTime and DateTimeImmutable
- Enum
- Another Entity
- Custom Class that implements `Composite\Entity\CastableInterface`

## Useful tips

* Only public and protected class properties are serializable, private will be ignored.
* Changed properties can be retrieved using the method `getChangedColumns`
* Old value can be retrieved using the method `getOldValue`
