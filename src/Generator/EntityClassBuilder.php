<?php declare(strict_types=1);

namespace Composite\DB\Generator;

use Composite\DB\AbstractEntity;
use Composite\DB\DateTimeHelper;
use Composite\DB\Generator\Schema\ColumnType;
use Composite\DB\Generator\Schema\SQLIndex;
use Composite\DB\Generator\Schema\SQLSchema;
use Composite\DB\Generator\Schema\SQLColumn;

class EntityClassBuilder
{
    private array $useNamespaces = [
        AbstractEntity::class,
    ];
    private array $useAttributes = [
        'Table',
    ];

    public function __construct(
        private readonly SQLSchema $schema,
        private readonly string $dbName,
        private readonly string $entityClass,
        private readonly array $enums,
    ) {}

    /**
     * @throws \Exception
     */
    public function getClassContent(): string
    {
        return $this->renderTemplate('EntityTemplate', $this->getVars());
    }

    /**
     * @throws \Exception
     */
    private function getVars(): array
    {
        $traits = $properties = [];
        $constructorParams = $this->getEntityProperties();
        if (!empty($this->schema->columns['deleted_at'])) {
            $traits[] = 'Traits\SoftDelete';
            $this->useNamespaces[] = 'Composite\DB\Entity\Traits';
            unset($constructorParams['deleted_at']);
        }
        foreach ($constructorParams as $name => $constructorParam) {
            if ($this->schema->columns[$name]->isAutoincrement) {
                $properties[$name] = $constructorParam;
                unset($constructorParams[$name]);
            }
        }
        if (!preg_match('/^(.+)\\\(\w+)$/', $this->entityClass, $matches)) {
            throw new \Exception("Entity class `$this->entityClass` is incorrect");
        }

        return [
            'phpOpener' => '<?php declare(strict_types=1);',
            'dbName' => $this->dbName,
            'tableName' => $this->schema->tableName,
            'pkNames' => "'" . implode("', '", $this->schema->primaryKeys) . "'",
            'indexes' => $this->getIndexes(),
            'traits' => $traits,
            'entityNamespace' => $matches[1],
            'entityClassShortname' => $matches[2],
            'properties' => $properties,
            'constructorParams' => $constructorParams,
            'useNamespaces' => array_unique($this->useNamespaces),
            'useAttributes' => array_unique($this->useAttributes),
        ];
    }

    private function getEntityProperties(): array
    {
        $noDefaultValue = $hasDefaultValue = [];
        foreach ($this->schema->columns as $column) {
            $attributes = [];
            if ($this->schema->isPrimaryKey($column->name)) {
                $this->useAttributes[] = 'PrimaryKey';
                $autoIncrement = $column->isAutoincrement ? '(autoIncrement: true)' : '';
                $attributes[] = '#[PrimaryKey' . $autoIncrement . ']';
            }
            if ($columnAttributeProperties = $column->getColumnAttributeProperties()) {
                $this->useAttributes[] = 'Column';
                $attributes[] = '#[Column(' . implode(', ', $columnAttributeProperties) . ')]';
            }
            $propertyParts = [$this->getPropertyVisibility($column)];
            if ($this->isReadOnly($column)) {
                $propertyParts[] = 'readonly';
            }
            $propertyParts[] = $this->getColumnType($column);
            $propertyParts[] = '$' . $column->name;
            if ($column->hasDefaultValue) {
                $defaultValue = $this->getDefaultValue($column);
                $propertyParts[] = '= ' . $defaultValue;
                $hasDefaultValue[$column->name] = [
                    'attributes' => $attributes,
                    'var' => implode(' ', $propertyParts),
                ];
            } else {
                $noDefaultValue[$column->name] = [
                    'attributes' => $attributes,
                    'var' => implode(' ', $propertyParts),
                ];
            }
        }
        return array_merge($noDefaultValue, $hasDefaultValue);
    }

    private function getPropertyVisibility(SQLColumn $column): string
    {
        return 'public';
    }

    private function isReadOnly(SQLColumn $column): bool
    {
        if ($column->isAutoincrement) {
            return true;
        }
        $readOnlyColumns = array_merge(
            $this->schema->primaryKeys,
            [
                'created_at',
                'createdAt',
            ]
        );
        return in_array($column->name, $readOnlyColumns);
    }

    private function getColumnType(SQLColumn $column): string
    {
        if ($column->type === ColumnType::Enum) {
            if (!$type = $this->getEnumName($column->name)) {
                $type = 'string';
            }
        } else {
            $type = $column->type->value;
        }
        if ($column->isNullable) {
            $type = '?' . $type;
        }
        return  $type;
    }

    public function getDefaultValue(SQLColumn $column): mixed
    {
        $defaultValue = $column->defaultValue;
        if ($defaultValue === null) {
            return 'null';
        }
        if ($column->type === ColumnType::Datetime) {
            $currentTimestamp = stripos($defaultValue, 'current_timestamp') === 0 || $defaultValue === 'now()';
            if ($currentTimestamp) {
                $defaultValue = "new \DateTimeImmutable()";
            } else {
                if ($defaultValue === 'epoch') {
                    $defaultValue = '1970-01-01 00:00:00';
                } elseif ($defaultValue instanceof \DateTimeInterface) {
                    $defaultValue = DateTimeHelper::dateTimeToString($defaultValue);
                }
                $defaultValue = "new \DateTimeImmutable('" . $defaultValue . "')";
            }
        } elseif ($column->type === ColumnType::Enum) {
            if ($enumName = $this->getEnumName($column->name)) {
                $valueName = null;
                /** @var \UnitEnum $enumClass */
                $enumClass = $this->enums[$column->name];
                foreach ($enumClass::cases() as $enumCase) {
                    if ($enumCase->name === $defaultValue) {
                        $valueName = $enumCase->name;
                    }
                }
                if ($valueName) {
                    $defaultValue = $enumName . '::' . $valueName;
                } else {
                    return 'null';
                }
            } else {
                $defaultValue = "'$defaultValue'";
            }
        } elseif ($column->type === ColumnType::Boolean) {
            if (strcasecmp($defaultValue, 'false') === 0) {
                return 'false';
            }
            if (strcasecmp($defaultValue, 'true') === 0) {
                return 'true';
            }
            return !empty($defaultValue) ? 'true' : 'false';
        } elseif ($column->type === ColumnType::Array) {
            if ($defaultValue === '{}' || $defaultValue === '[]') {
                return '[]';
            }
            if ($decoded = json_decode($defaultValue, true)) {
                return var_export($decoded, true);
            }
            return $defaultValue;
        } else {
            if ($column->type !== ColumnType::Integer && $column->type !== ColumnType::Float) {
                $defaultValue = "'$defaultValue'";
            }
        }
        return $defaultValue;
    }

    private function getEnumName(string $columnName): ?string
    {
        if (empty($this->enums[$columnName])) {
            return null;
        }
        $enumClass = $this->enums[$columnName];
        if (!\in_array($enumClass, $this->useNamespaces)) {
            $this->useNamespaces[] = $enumClass;
        }
        return substr(strrchr($enumClass, "\\"), 1);
    }

    private function getIndexes(): array
    {
        $result = [];
        foreach ($this->schema->indexes as $index) {
            $properties = [
                "columns: ['" . implode("', '", $index->columns) . "']",
            ];
            if ($index->isUnique) {
                $properties[] = "isUnique: true";
            }
            if ($index->sort) {
                $sortParts = [];
                foreach ($index->sort as $key => $direction) {
                    $sortParts[] = "'$key' => '$direction'";
                }
                $properties[] = 'sort: ['. implode(', ', $sortParts) . ']';
            }
            if ($index->name) {
                $properties[] = "name: '" . $index->name . "'";
            }
            $this->useAttributes[] = 'Index';
            $result[] = '#[Index(' . implode(', ', $properties) . ')]';
        }
        return $result;
    }

    private function renderTemplate(string $templateName, array $variables = []): string
    {
        $filePath = implode(
            DIRECTORY_SEPARATOR,
            [
                __DIR__,
                'Templates',
                "$templateName.php",
            ]
        );
        extract($variables, EXTR_SKIP);
        ob_start();
        include $filePath;
        return ob_get_clean();
    }
}